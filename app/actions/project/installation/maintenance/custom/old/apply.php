<?php

// filter config
$config = array();
foreach (explode("\n", $ARG) as $line) {
    if (stripos($line, 'ISMDEV_MAINTANCE')) {
        $config[] = $line;
    }
}
$config = implode("\n", $config);

if (!$inst->execOld('cat .htaccess')) {
    error("Can\'t load .htaccess");
}
$htAccess = $inst->execOutput;

// update .htaccess
$updatedHtAccess = array();
// remove current maintenance configuration
foreach (explode("\n", $htAccess) as $line) {
    if (stripos($line, 'ISMDEV_MAINTANCE')) {
        continue;
    }
    $updatedHtAccess[] = $line;
}
$updatedHtAccess = trim(implode("\n", $updatedHtAccess));
$updatedHtAccess = "\n$config\n\n\n" . $updatedHtAccess;

if (!$inst->execOld('echo %s > .htaccess', $updatedHtAccess)) {
    error('Can\'t update .htaccess');
}
