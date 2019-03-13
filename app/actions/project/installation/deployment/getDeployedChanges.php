<?php

$changesLimit = 2000;

$changes = $localInst->git->getCleanRevsDiff(
    $deployment->remoteHashBeforeDeployment,
    $deployment->mergeHash,
    $changesLimit + 1
);

$changesLimitExceeded = false;
if (count($changes) > $changesLimit) {
    $changesLimitExceeded = true;
    end($changes);
    unset($changes[key($changes)]);
}

$error = $changes === false ? $inst->execOutput : '';

// count depins
$depins = array();
if ($changes) {
    foreach ($changes as $change) {
        if (
            in_array($change['type'], array('A', 'M'))
            && preg_match('~/depins/~', $change['file'])
            && ($depinName = $inst->deployment->getDepinName($change['file']))
        ) {
            $depins[] = array(
                'file' => $change['file'],
                'name' => $depinName,
                'author' => $localInst->git->getFileLastCommit($change['file'])['author']['name'],
                'isNew' => ($change['type'] === 'A')
            );
        }
    }
}

deploymentDialog('deployedChanges', compact('changes', 'changesLimit', 'changesLimitExceeded', 'error', 'depins'));
