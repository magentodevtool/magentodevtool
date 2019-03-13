<?php

try {
    if ($inst->isCloud) {
        $hash = $localInst->git->getBranchHash($inst->cloud->branch);
    } else {
        $hash = $inst->git->getCurrentHash();
    }
} catch (Exception $e) {
    deploymentDialog('failedToSaveRemoteHash', ['details' => $e->getMessage()]);
}

deploymentDialog('saveRemoteHash', compact('hash'));