<?php

$currentRemoteBranch = $deployment->currentRemoteBranch;

try {
    $cmd = ['git push -f origin %s:%s']; // -f to make resetEnvironment flag working
    if ($deployment->type === 'production') {
        if ($inst->isCloud) {
            $cmd[] = 'git push origin production:production';
        } else {
            $cmd[] = 'git push origin Live:Live';
        }
    }

    $localInst->exec($cmd, $currentRemoteBranch, $currentRemoteBranch);
} catch (Exception $e) {
    deploymentDialog('failedToPush', array('details' => $e->getMessage()));
}