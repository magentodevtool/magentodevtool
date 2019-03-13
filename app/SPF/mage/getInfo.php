<?php

namespace SPF\mage;

function getInfo()
{
    // not env.php because can be removed on 2.2+ deployment environment to make config:dump
    if (file_exists('app/etc/config.php')) {
        return getInfoM2();
    } else {
        return getInfoM1();
    }
}

function getInfoM2()
{
    $vendorComposerInstalled = 'vendor/composer/installed.json';
    $version = 'Undefined';
    $edition = 'Undefined';
    $cePackage = 'magento/product-community-edition';
    $eePackage = 'magento/product-enterprise-edition';

    if (file_exists($vendorComposerInstalled)) {
        $installed = (object)array();
        foreach (json_decode(file_get_contents($vendorComposerInstalled)) as $package) {
            $installed->{$package->name} = $package;
        }
        if (isset($installed->$eePackage)) {
            $edition = 'Enterprise';
            $version = $installed->$eePackage->version;
        } else {
            $edition = 'Community';
            $version = $installed->$cePackage->version;
        }
    } elseif (file_exists('composer.json')) {
        $composerConfig = json_decode(file_get_contents('composer.json'));
        foreach ($composerConfig->require as $key => $value) {
            if ($key === $eePackage) {
                $edition = 'Enterprise';
                $version = $value;
            }
            if ($key === $cePackage) {
                $edition = 'Community';
                $version = $value;
            }
        }
    }
    return (object)array(
        'edition' => $edition,
        'version' => $version,
        'patches' => getPatchesInfo(),
    );
}

function getInfoM1()
{
    require_once 'app/Mage.php';

    $edition = file_exists('app/code/core/Enterprise') ? 'Enterprise' : 'Community';
    $version = \Mage::getVersion();

    return (object)array(
        'edition' => $edition,
        'version' => $version,
        'patches' => getPatchesInfo(),
    );
}

function getPatchesInfo()
{
    if (!file_exists('app/etc/applied.patches.list')) {
        return array();
    }

    $content = file_get_contents('app/etc/applied.patches.list');
    if (!preg_match_all('~SUPEE-\d+~ms', $content, $matches)) {
        return array();
    }

    return count($matches) ? array_unique($matches[0]) : array();
}
