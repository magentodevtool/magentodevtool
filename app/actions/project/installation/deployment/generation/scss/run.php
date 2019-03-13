<?php

$error = false;
$isAvailable = $localInst->generation->scss->isAvailable();
$nothingToCompile = !count($localInst->generation->scss->getThemes());
$nothingToCommit = false;

try {
    if ($isAvailable && !$nothingToCompile) {
        $localInst->exec("git reset --hard");
        $localInst->generation->scss->run();
        $nothingToCommit = !$localInst->generation->scss->commit();
        $localInst->generation->scss->rmCache();
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

deploymentDialog(
    'generation/scss/result',
    compact('error', 'isAvailable', 'nothingToCommit', 'nothingToCompile')
);
