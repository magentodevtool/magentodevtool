<?php

ini_set('max_execution_time', 0);
ini_set('max_input_time', -1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

$mRoot = __DIR__ . '/../..';
if ($instInfo->project->type === 'magento2') {
    $mRoot .= '/..';
}
define('MAGE_ROOT', realpath($mRoot) . '/');

require_once 'functions.php';

auth();
