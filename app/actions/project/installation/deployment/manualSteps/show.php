<?php

if (empty($inst->project->deployment->isManualStepsRequired)) {
    return 1;
}

// push everything what is done by devtool automatically so that it will be visible on developer environment
// also in order to avoid possible conflict after pull on centralized devtool
$currentBranch = $localInst->git->getCurrentBranch();
$localInst->exec('git push origin %s:%s', $currentBranch, $currentBranch);

deploymentDialog('manualSteps', compact('inst', 'deployment'));
