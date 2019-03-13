<?php

try {

    $cmd = [
        "git checkout production",
        "git reset --hard origin/production",
        "git merge --no-ff master",
    ];
    $localInst->exec($cmd);

} catch (\Exception $e) {
    try {
        $inst->exec('git merge --abort');
    } catch (Exception $e2) {
    }
    deploymentDialog('failedToUpdateProductionBranch', array('details' => $e->getMessage()));
}
