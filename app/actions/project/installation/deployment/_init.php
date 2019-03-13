<?php

global $deployment;

$deployment = $ARG;
$remoteInst = $inst;
$localInst = $remoteInst->deployment->localInstallation->get();

$deploymentAction = preg_replace('~^project/installation/deployment/~', '', $actionName);
$noLockActions = [
    'customNotes/markAsRead',
    'validateForm',
    'localInstallation/prepareRepo',
    'localInstallation/checkRepoUrl',
    'lock/capture',
    'lock/recapture',
    'getDeployedChanges',
];

if (
    !$_POST['underground']
    && !in_array($deploymentAction, $noLockActions)
    && !$inst->deployment->lock->isWritable($deployment->lockHash)
) {
    if (!$deployment->remoteHashBeforeDeployment) {
        // lock/recapture action requires remote hash for safety check
        error("Deployment lock has been lost. Recapture isn't available on this step");
    }
    deploymentDialog('dialog/lockHasBeenLost');
}

function deploymentDialog($code, $params = array())
{
    error(template('project/installation/forms/default/deployment/' . $code, $params), true);
}
