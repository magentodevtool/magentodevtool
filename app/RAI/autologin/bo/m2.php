<?php

require_once '../init.php';

$bootstrap = initMagento2();
$objectManager = $bootstrap->getObjectManager();

$getAdminUserByLogin = function ($login, $email) use ($objectManager) {
    $db = $objectManager->get('Magento\Framework\App\ResourceConnection')->getConnection();
    return $db->query(
        "select * from admin_user where username = ? or email = ? limit 1",
        [$login, $email]
    )->fetch(PDO::FETCH_ASSOC);
};

/** @var string $ldapUser */
global $ldapUser;
$userEmail = $ldapUser . '@yourcompany.com';

$user = $objectManager->get('Magento\User\Model\User');
if ($userData = $getAdminUserByLogin($ldapUser, $userEmail)) {
    // Get existed user
    $user->load($userData['user_id']);
} else {
    // Create new user
    $userData = [
        'username' => $ldapUser,
        'password' => md5(rand()),
        'email' => $userEmail,
        'firstname' => 'ISM',
        'lastname' => 'Employee'
    ];

    $db = $objectManager->get('Magento\Framework\App\ResourceConnection')->getConnection();
    $roleId = reset($db->query("select role_id from authorization_role order by role_id limit 1")->fetch(PDO::FETCH_ASSOC));
    $user->setData($userData)->setRoleId($roleId)->save();
}

// Copy of Magento\Backend\Model\Auth::login
$authStorage = $objectManager->get('Magento\Backend\Model\Auth\StorageInterface');
$authStorage->setUser($user);
$authStorage->processLogin();

$eventManager = $objectManager->get('Magento\Framework\Event\ManagerInterface');
$eventManager->dispatch(
    'backend_auth_user_login_success',
    ['user' => $user]
);

// Magento 2.1 security plugin
try {
    $securityAuthPlugin = $objectManager->get('Magento\Security\Model\Plugin\Auth');
    $securityAuthPlugin->afterLogin($objectManager->get('Magento\Backend\Model\Auth'));
} catch (\Exception $e) {
    // exception will be thrown for Magento version < 2.0.1
}

$boPathEscaped = preg_replace('~[\n\r]~', '', $_POST['boPath']);
header("Location: /$boPathEscaped");
