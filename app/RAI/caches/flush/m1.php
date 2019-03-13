<?php

require_once '../init.php';

$options = json_decode($_GET['options']);
if (!is_object($options)) {
    error('Invalid options');
}

// APC must be flushed before any attempt to use Magento API (problem was detected on Sport2000 where die('database update..') line was added into Mage.php)
if ($options->mode === 'all' || $options->flush->apc) {
    if (function_exists('apc_clear_cache')) {
        apc_clear_cache();
    }
}

require_once MAGE_ROOT . 'app/Mage.php';

// disable cache to avoid fatal errors when some model gone but stay in XML
Mage::app('admin', 'store', array('global_ban_use_cache' => true));

function flushRunnerCache()
{
    // @ added to prevent magento autoloader warning in system.log if class does not exist
    if (!@class_exists('ISM_Base_Runner_Model_Observer')) {
        return;
    }
    $observer = new ISM_Base_Runner_Model_Observer();
    if (!is_callable(array($observer, 'cleanCache'))) {
        return;
    }
    $observer->cleanCache(new stdClass());
}

$updatesException = false;

try {

    // flush css, js
    if ($options->mode === 'all' || $options->flush->cssAndJs) {
        Mage::getModel('core/design_package')->cleanMergedJsCss();
    }

    // flush storage
    if ($options->mode === 'all' || $options->flush->storage) {

        Mage::app()->getCacheInstance()->flush();
        Mage::app()->cleanCache(); // added coz redis on sissiboy is not flushed by previous line

        flushRunnerCache();

        //// apply updates
        // backup core_resource
        $core_resource_before = db(getDbCredentials())->query('select * from core_resource')->fetchAll();

        ob_start();
        try {
            // load new module versions before apply updates
            Mage::app()->getConfig()->loadModules();
            // pass true if safeinstaller
            Mage_Core_Model_Resource_Setup::applyAllUpdates(true);
            Mage_Core_Model_Resource_Setup::applyAllDataUpdates(true);
        } catch (\Exception $e) {
            $updatesException = $e->getMessage();
        }
        // magento print exception in Core/Model/Resource/Setup.php, clean it.
        ob_clean();

        // find updates info
        $core_resource_after = db()->query('select * from core_resource')->fetchAll();
        $updates = findUpdates($core_resource_before, $core_resource_after);

    }

    // FPC
    if ($options->mode === 'all' || $options->flush->fpc) {
        if (Mage::getConfig()->getModuleConfig('Enterprise_PageCache')) {
            Mage::getSingleton('enterprise_pagecache/observer')->cleanCache();
        }
    }

    // Images
    if ($options->mode === 'specific' && $options->flush->images) {
        Mage::getModel('catalog/product_image')->clearCache();
    }


} catch (Exception $e) {

    error($e->getMessage());

}

die(json_encode(
    (object)array(
        'updates' => @$updates,
        'updatesException' => $updatesException
    )
));


function findUpdates($core_resource_before, $core_resource_after)
{
    $a = array();
    $b = array();
    foreach ($core_resource_before as $row) {
        $a[$row['code']] = $row;
    }
    foreach ($core_resource_after as $row) {
        $b[$row['code']] = $row;
    }

    $updates = array();
    foreach ($b as $bCode => $bRow) {
        if (!isset($a[$bCode])) {
            $updates[$bCode] = array('from' => '0.0.0', 'to' => $bRow['version']);
            continue;
        }
        if ($a[$bCode]['version'] !== $bRow['version']) {
            $updates[$bCode] = array('from' => $a[$bCode]['version'], 'to' => $bRow['version']);
            continue;
        }
    }
    return $updates;
}