<?php

require_once '../init.php';

$email = trim($_POST['email']);
$fePath = trim($_POST['fePath']);
$storesCode = explode(',', $_POST['urlStores']);
$storesCode[] = 'admin'; // if customer created from BO
if (empty($email) || !count($storesCode) || empty($fePath)) {
    throw new Exception('Invalid input parameters');
}

// init db
db(getDbCredentials());

$shareScope = getCustomerShareScope();

$customerSql = "
    select * from customer_entity
    left join core_store on (customer_entity.store_id = core_store.store_id)
    where email = " . db()->quote($email);

if ($shareScope === 'website') {
    $storesCodeFilter = '';
    $sep = '';
    foreach ($storesCode as $storeCode) {
        $storesCodeFilter .= $sep . db()->quote($storeCode);
        $sep = ', ';
    }
    $customerSql .= "\n     and core_store.code in ($storesCodeFilter)";
}

$customerSql .= "\n     order by if(core_store.code = 'admin', 1, 0)";

$customer = db()->query($customerSql)->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    echo 'Customer "' . $email . '" not found';
    if ($shareScope === 'website') {
        echo ' in scope of stores: "' . implode(', ', $storesCode) . '"';
    }
    die;
}

// fix magento cookie path for session
$_SERVER['SCRIPT_FILENAME'] = getcwd() . '/index.php';
chdir(MAGE_ROOT);
require 'app/Mage.php';
Mage::app();
// disable runner sanity check (it will be called when getSingleton('core/session') which init front controller)
define('ISM_BASE_RUNNER_INCLUDED', true);

$sessionParams = array('name' => 'frontend');
// dirty fix for solid
if (isSolidSessionStartBug()) {
    $sessionParams = array();
}

// init Magento session
Mage::getSingleton('core/session', $sessionParams);
// get customer session namespace
$session = Mage::getSingleton('customer/session');

$session->setId($customer['entity_id']);

if (Mage::getConfig()->getModuleConfig('Enterprise_PageCache')) {
    Mage::getSingleton('enterprise_pagecache/cookie')->updateCustomerCookies();
}

$fePathEscaped = preg_replace('~[\n\r]~', '', $fePath);
header("Location: $fePathEscaped");
