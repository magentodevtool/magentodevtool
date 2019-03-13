<?php

$envFile = $localInst->folder . 'app/etc/env.php';
$backupEnvFile = $localInst->folder . "var/env.php.m21backup";
if ($deployment->mageVersion >= '2.2') {
    if (file_exists($envFile)) {
        // backup env.php, because it still can be needed for M2.1 deployments
        rename($envFile, $backupEnvFile);
    }
    return 1;
}

if (!file_exists($envFile) && file_exists($backupEnvFile)) {
    // restore backup after previous M2.2 deployment
    rename($backupEnvFile, $envFile);
}

$doEnvFileExistsBeforeFixDistFiles = file_exists($envFile);

$localInst->fixDistFiles();

$doEnvFileExistsAfterFixDistFiles = file_exists($envFile);

if (!$doEnvFileExistsBeforeFixDistFiles && $doEnvFileExistsAfterFixDistFiles) {
    // remove dist env file, there may be configurations which will fail bin/magento e.g. redis, memcache etc
    unlink($envFile);
}

if (!file_exists($envFile)) {
    copy(__DIR__ . '/configure/envSample.php', $envFile);
}

if (!file_exists($envFile)) {
    error("File '$envFile' not found");
}
