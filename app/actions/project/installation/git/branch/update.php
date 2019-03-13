<?php

$currentBranch = $ARG->currentBranch;

if ($inst->git->isUnfinishedMerge()) {
    error('Unfinished merge detected on current branch, finish it or abort');
}
if (count($inst->git->getBranchBehindCommits($currentBranch))) {
    error('You need to pull first');
}
if (count($inst->git->getBranchAheadCommits($currentBranch))) {
    error('You need to push first');
}

// MERGE
try {
    if ($inst->generation->scss->isAvailable()) {
        $hashBefore = $inst->git->getCurrentHash();
    }
    $inst->exec(array(
        // free index for merge in order to reduce probability of error messages "your local changes will be overwritten"
        'git reset',
        'git merge --no-ff origin/master',
    ));
} catch (Exception $e) {
    try {
        $inst->exec('git merge --abort');
    } catch (Exception $e2) {
    }
    throw $e;
}

// PUSH MERGE
$inst->exec('git push origin %s', $currentBranch);

if ($inst->generation->scss->isAvailable()) {
    try {
        $hashAfter = $inst->git->getCurrentHash();
        $inst->generation->scss->runOnSourcesChange($hashBefore, $hashAfter);
    } catch (Exception $e) {
        return $inst->form('git/sourcesChangeResult', ['scssCompilationException' => $e]);
    }
}
