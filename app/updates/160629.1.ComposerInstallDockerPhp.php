<?php

$lockFile = APP_DIR . '../composer.lock';

if (file_exists($lockFile)) {
    $composerLock = json_decode(file_get_contents($lockFile));
    if (isset($composerLock->packages)) {
        foreach ($composerLock->packages as $package) {
            if ($package->name === "docker-php/docker-php") {
                return;
            }
        }
    }
}

die('You need to execute "composer install in the Devtool folder"');
