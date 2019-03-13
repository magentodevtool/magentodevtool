<?php

$currentBranch = $deployment->currentRemoteBranch;
$resetWarnings = array();

try {
    $inst->git->fetch();
    $resetWarnings = $inst->git->hardReset('origin/' . $currentBranch);
} catch (Exception $e) {
    deploymentDialog('failedToPullRemote', array('details' => $e->getMessage()));
}

if (count($resetWarnings)) {
    deploymentDialog('pullWarnings', array('details' => implode("\n", $resetWarnings)));
}
