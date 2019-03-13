<?php

require_once '../init.php';
$pdo = db(getDbCredentials());
$userEmail = $ldapUser . '@yourcompany.com';
$adminUser = $pdo->query("select * from admin_user where username = {$pdo->quote($ldapUser)} or email = {$pdo->quote($userEmail)} limit 1")->fetch();
$adminUserId = $adminUser ? $adminUser['user_id'] : null;
$boPath = $_POST['boPath'];

// fix magento cookie path for session
$_SERVER['SCRIPT_FILENAME'] = getcwd() . '/index.php';
chdir(MAGE_ROOT);
require 'app/Mage.php';
Mage::app('admin');
// disable runner sanity check (it will be called when getSingleton('core/session') which init front controller)
define('ISM_BASE_RUNNER_INCLUDED', true);
define('ADMIN_ROLE_ID', 1);

$cookie = Mage::getSingleton('core/cookie');
$sessionParams = array('name' => 'adminhtml');
// dirty fix for solid
if (isSolidSessionStartBug()) {
    $sessionParams = array();
}

// init Magento session
try {
    Mage::getSingleton('core/session', $sessionParams);
} catch (Exception $e) {
    // try to create new session (sometimes session initialization throw Mage_Core_Model_Session_Exception due to extra validation settings)
    unset($_COOKIE['adminhtml']);
    unset($_COOKIE['PHPSESSID']);
    session_destroy();
    Mage::getSingleton('core/session', $sessionParams);
}

// get admin session namespace
$session = Mage::getSingleton('admin/session');
$user = Mage::getModel('admin/user');
if ($adminUserId !== null) {
    $user->load($adminUserId);
} else {
    // Create new user
    $userData = [
        'username' => $ldapUser,
        'password' => md5(rand()),
        'email' => $userEmail,
        'firstname' => 'ISM',
        'lastname' => 'Employee',
        'is_active' => 1,
    ];

    $user->setData($userData)->save();
    //set role "Administrator" to the new user
    $user->setRoleIds(array(ADMIN_ROLE_ID))->setRoleUserId($user->getUserId())->saveRelations();
}

// if IP column exists it's created by ISM_AccountSharing to comply GDPR
// and restrict access from other IP addresses
// relevant for ISM_AccountSharing version < 0.2.0
$ipColumnExists = $pdo->query("show columns from admin_user like 'IP'")->fetch();

if ($ipColumnExists) {
    $pdo->query(
        "UPDATE admin_user set IP = {$pdo->quote(Mage::helper('core/http')->getRemoteAddr())} 
         where user_id = {$pdo->quote($user->getId())}"
    );
}

// check if visitorHash column exists it's created by ISM_AccountSharing to comply GDPR
// relevant for ISM_AccountSharing version  0.2.0
$visitorHashColumnExists = $pdo->query("show columns from admin_user like 'visitorHash'")->fetch();

if ($visitorHashColumnExists) {
    $visitorHash = md5(rand());

    if ($cookie->get('visitorHash')) {
        $visitorHash = $cookie->get('visitorHash');
    } else {
        //set cookie expires in 10 year
        $cookie->set('visitorHash', $visitorHash, 60 * 60 * 24 * 365 * 10);
    }
    $pdo->query(
        "UPDATE admin_user set visitorHash = {$pdo->quote($visitorHash)} 
         where user_id = {$pdo->quote($user->getId())}"
    );
}

$session->setIsFirstPageAfterLogin(true);
$session->setUser($user);
$session->setAcl(Mage::getResourceModel('admin/acl')->loadAcl());
$_SESSION['adminhtml']['locale'] = 'en_US';

$boPathEscaped = preg_replace('~[\n\r]~', '', $boPath);
header("Location: /$boPathEscaped");
