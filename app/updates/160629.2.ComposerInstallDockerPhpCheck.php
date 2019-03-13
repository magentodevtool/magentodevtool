<?php

$application = new \Project\Installation\Composer\Application(null, APP_DIR . '../composer.json');

$composer = $application->getComposer();

if ($composer) {
    $repository = $composer->getRepositoryManager()->getLocalRepository();

    $package = $repository->findPackages('docker-php/docker-php');

    if ($package) {
        return;
    }
}

die('You need to execute "composer install" in the Devtool folder');
