<?php

global $instInfo;
require_once '../init.php';

$m2Config = getM2EnvConfig();
$mageMode = isset($m2Config['MAGE_MODE']) ? strtolower($m2Config['MAGE_MODE']) : '';
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

// clear opcode cache if exist
if ($options->mode === 'all' || $options->flush->opcache) {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
}

try {
    // Skip Cloud as it doesn't require js/css flush after deployment. Also removing merged folder leads to Fastly bug
    if (!$instInfo->isCloud && ($options->mode === 'all' || $options->flush->css_js)) {
        // css/js should be flushed before FPC
        exece('rm -rf pub/static/_cache/merged');
    };

    if ($options->mode === 'all') {
        exece('php bin/magento cache:flush');
    }

    if ($options->mode === 'specific' && $options->flush->full_page) {
        exece('php bin/magento cache:flush full_page');
    }

    if ($mageMode !== 'production' && ($options->mode === 'all' || $options->flush->di)) {
        exece('rm -rf var/di/* var/generation/* generated/*');
    }

    if ($options->mode === 'specific' && $options->flush->static_content) {
        exece('rm -rf var/view_preprocessed/* pub/static/*');
    }

    if ($options->mode === 'all') {
        // sometimes after deployment Magento doesn't work until you remove var/cache manually even if Magento cache storage aren't files
        exece('rm -rf var/cache/*');
    }
} catch (Exception $e) {
    $return['error'] = trim($e->getMessage());
}

$return['updates'] = array();
$return['updatesException'] = false;
echo json_encode($return);
