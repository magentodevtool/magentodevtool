<?php

require_once 'init.functions.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set(trim(file_get_contents('/etc/timezone')));

// disable html errors, var_dump and exceptions to make ajax responses more readable
ini_set('xdebug.overload_var_dump', 0);
ini_set('html_errors', 0);

ini_set('default_socket_timeout', 300);

// fix for escapeshellarg to don't cut non-ASCII characters
setlocale(LC_CTYPE, "en_US.UTF-8");

$user = posix_getpwuid(getmyuid());
define('USER', $user['name']);
define('USER_HOME', $user['dir'] . '/');
putenv('HOME=' . USER_HOME);
putenv('PATH=/usr/share/centrifydc/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:');

define('APP_DIR', __DIR__ . '/');
define('TPL_DIR', APP_DIR . 'templates/');
define('ACTIONS_DIR', APP_DIR . 'actions/');
define('INSTANCE_NAME', isset($_SERVER['INSTANCE_NAME']) ? $_SERVER['INSTANCE_NAME'] : 'devtool');
define('DATA_DIR', USER_HOME . '.' . INSTANCE_NAME . '/');
define('DATA_DIR_INT', USER_HOME . '.' . INSTANCE_NAME . '/.internal/');
define('DATA_DIR_INT_LOCK', USER_HOME . '.' . INSTANCE_NAME . '/.internal/lock/');
define('TMP_DIR', '/tmp/' . INSTANCE_NAME . '/');

define('PATH_CONFIG', realpath(APP_DIR . 'system/config/'));
define('PATH_CONFIG_NGINX', PATH_CONFIG . '/nginx/');
define('PATH_CONFIG_FPM', PATH_CONFIG . '/php5/fpm/');

//define('CENTR_HOST', 'http://devtool.local/');
define('CENTR_HOST', 'http://centralized-devtool-url.com/');

define('TE_API_KEY', '8ialUhYDEnzA5pq7DRP2vvoU4+7klo9o');

header('Content-type: text/html; charset=UTF-8');

// do slow checks only on pages (no ajax actions)
if (!isset($_GET['action'])) {

    // check user
    if (USER == 'www-data') {
        die('Error: I\'m www-data, its not enough, please give me your rights');
    }

    // check sudo
    exec('sudo echo 123', $o, $r);
    if ($r !== 0) {
        die('Error: You should have sudo without password, add following line to /etc/sudoers "' . USER . '  ALL=NOPASSWD:  ALL"');
    }

    checkExtensions();

}

// check user agent
if (!preg_match('~chrome|firefox~i', $_SERVER['HTTP_USER_AGENT'])) {
    die('Sorry, only Chrome and Firefox are supported at the moment');
}

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR);
}
if (!is_dir(DATA_DIR_INT)) {
    mkdir(DATA_DIR_INT);
}
if (!is_dir(DATA_DIR_INT_LOCK)) {
    mkdir(DATA_DIR_INT_LOCK);
}
if (!is_dir(TMP_DIR)) {
    @mkdir(TMP_DIR);
}

includeDir(APP_DIR . 'core');

$autoloadFile = APP_DIR . '../vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    die('Error: vendor/autoload.php not found. You need to run "composer install" in the Devtool folder');
}
require_once($autoloadFile);

auth();

define('LDAP_USER', isset($_SERVER['PHP_AUTH_USER']) ? preg_replace('~^.+\\\\~', '', $_SERVER['PHP_AUTH_USER']) : USER);

Updates::apply();

Events::register('action.dispatch.before', array('ACL', 'actionDispatchBefore'));
Events::register('action.dispatch.before', array('Log', 'actionDispatchBefore'));
Events::register('page.render.before', array('Log', 'pageRenderBefore'));
