<?php

/** @var \Project\Installation\Deployment $deployment */

$beforeDepins = array();

$analyzeChanges = function ($branch, $changes) use ($localInst, &$beforeDepins) {
    foreach ($changes as $change) {
        if ($change['type'] === 'A' && $name = $localInst->deployment->getDepinName($change['file'], 'pre')) {
            $localInst->execOld('git show %s:%s', "origin/$branch", $change['file']);
            $content = $localInst->execOutput;
            $beforeDepins[] = array(
                'name' => $name,
                'content' => $content,
            );
        }
    }
};

foreach ($deployment->branchesToDeploy as $branch) {
    $changes = $localInst->git->getCleanRevsDiff('origin/' . $deployment->currentRemoteBranch, "origin/$branch");
    $analyzeChanges($branch, $changes);
}

// check for new pre depin in master
$changes = $localInst->git->getCleanRevsDiff(
    $deployment->remoteHashBeforeDeployment,
    "origin/master"
);
$analyzeChanges('master', $changes);

if (count($beforeDepins)) {
    deploymentDialog('beforeDepins', array('depins' => $beforeDepins));
}
