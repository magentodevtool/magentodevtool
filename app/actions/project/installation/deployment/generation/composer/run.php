<?php

$error = false;
$composer = $localInst->generation->composer;

$isAvailable = $composer->isAvailable();
$nothingToInstall =
    !$composer->areSourcesChanged(
        $deployment->remoteHashBeforeDeployment,
        $deployment->mergeHash
    )
    && $composer->wasDoneInBranch();
$nothingToCommit = false;

try {
    if ($isAvailable && !$nothingToInstall) {
        $localInst->exec("git reset --hard");
        $composer->run(true);
        $nothingToCommit = !$composer->commit();
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

deploymentDialog(
    'generation/composer/result',
    compact('error', 'isAvailable', 'nothingToCommit', 'nothingToInstall')
);
