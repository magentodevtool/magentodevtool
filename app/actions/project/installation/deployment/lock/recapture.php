<?php

global $deployment;
$error = $hashes = false;

try {
    if ($deployment->remoteHashBeforeDeployment !== $inst->git->getCurrentHash()) {
        error('Deployment was done meanwhile by somebody else. You need to restart your deployment.');
    }
    $hashes = $inst->deployment->lock->capture();

} catch (\Exception $e) {
    $error = $e->getMessage();
}

if (!$error && !$hashes) {
    try {
        $lockInfo = $inst->deployment->lock->getInfo();
    } catch (\Exception $e) {
        $error = 'failed to fetch the details: ' . $e->getMessage();
    }
    if (!$error && !$lockInfo) {
        $error = 'no details were provided';
    }
    if (!$error) {
        $error = $lockInfo->user . ' is deploying to ' . $lockInfo->for . ', please wait..';
    }
}

if ($hashes === false) {
    deploymentDialog('lock/recapture/failed', compact('error'));
} else {
    deploymentDialog('lock/recapture/success', compact('hashes'));
}
