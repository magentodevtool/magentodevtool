<?php

/** @var \Project\Installation $localInst */

if ($localInst->execOld('git show MERGE_HEAD')) {
    $localInst->exec('git merge --abort');
}

// add local modification to index
if (count($localInst->git->getModifications()->diff)) {
    // reset all modifications
    $localInst->exec('git reset --hard');
}

try {
    $localInst->git->fetch();
} catch (Exception $e) {
    deploymentDialog('failedToFetchLocal', array('details' => $e->getMessage()));
}

$currentRemoteBranch = $deployment->currentRemoteBranch;
$branchesToDeploy = $deployment->branchesToDeploy;

/*
 * Sync remote branch on local inst
 */
$resetToBranch = $deployment->resetEnvironment ? 'master' : $currentRemoteBranch;
$cmd = cmd(array(
    'git checkout %s',
    'git reset --hard %s'
), $currentRemoteBranch, "origin/$resetToBranch");

if (!$localInst->execOld($cmd)) {
    deploymentDialog('failedToSyncBranch', array('branch' => $currentRemoteBranch, 'cmd' => $cmd));
}

/*
 * Merge
 */
$branchesArgs = array_map(
    function ($branch) {
        return escapeshellarg("origin/$branch");
    },
    $branchesToDeploy
);
$cmd = "git merge --no-ff " . implode(' ', $branchesArgs);

if (!$localInst->execOld($cmd)) {

    $mergeOutput = $localInst->execOutput;

    $unmergedFiles = array();
    $localInst->execOld('git status --porcelain');
    foreach (explode("\n", $localInst->execOutput) as $line) {
        $fileState = substr($line, 0, 2);
        $file = substr($line, 3);
        if (in_array($fileState, array('DD', 'AU', 'UD', 'UA', 'DU', 'AA', 'UU'))) {
            $unmergedFiles[] = array('state' => $fileState, 'file' => $file);
        }
    }

    if (!count($unmergedFiles)) {
        $localInst->execOld('git merge --abort');
        error("<br>Merge failed:<br>" . $mergeOutput, true);
    } else {
        deploymentDialog('conflicts', array('unmergedFiles' => $unmergedFiles));
    }
}

$mergeHash = trim($localInst->exec('git rev-parse HEAD'));
deploymentDialog('succeedMerge', compact('mergeHash'));
