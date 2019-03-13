<?php

$currentBranch = $inst->git->getCurrentBranch();
if ($inst->generation->scss->isAvailable()) {
    $hashBefore = $inst->git->getCurrentHash();
}
$inst->exec('git pull origin %s', $currentBranch);

if ($inst->generation->scss->isAvailable()) {
    try {
        $hashAfter = $inst->git->getCurrentHash();
        $inst->generation->scss->runOnSourcesChange($hashBefore, $hashAfter);
    } catch (Exception $e) {
        return $inst->form('git/sourcesChangeResult', ['scssCompilationException' => $e]);
    }
}
