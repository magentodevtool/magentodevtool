<?php

try {
    $branches = $localInst->git->getRemoteBranches();
    $cmd = array();

    if (!isset($branches['Live'])) {
        $cmd[] = "git push origin master:Live";
    }

    $cmd = array_merge($cmd, array(
        "git checkout Live",
        "git reset --hard origin/Live",
        "git merge --no-ff master",
    ));

    $localInst->exec($cmd);

} catch (\Exception $e) {
    try {
        $inst->exec('git merge --abort');
    } catch (Exception $e2) {
    }
    deploymentDialog('failedToUpdateLiveBranch', array('details' => $e->getMessage()));
}
