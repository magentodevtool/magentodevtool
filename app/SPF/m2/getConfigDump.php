<?php

# incspf exec
# incspf error

namespace SPF\m2;

function getConfigDump()
{
    $envFile = './app/etc/env.php';
    $configFile = './app/etc/config.php';
    \SPF\exec('git checkout HEAD ' . $configFile);
    // backup env.php
    if (!copy($envFile, $envFile . '.autobak')) {
        \SPF\error('Failed to backup env.php file on remote');
    }
    // create dump to config.php and env.php
    \SPF\exec('php bin/magento app:config:dump');
    $config = include $configFile;
    // restore original config.php and env.php
    \SPF\exec('git checkout HEAD ' . $configFile);
    if (!rename($envFile . '.autobak', $envFile)) {
        \SPF\error('Failed to restore env.php file on remote');
    }
    // restore config hashes from restored config.php
    \SPF\exec('php bin/magento app:config:import');

    return $config;
}
