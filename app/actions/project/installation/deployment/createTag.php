<?php

if ($inst->isCloud) {
    // TODO: validation for nothingToDeploy
    $localInst->git->createTag(
        $deployment->newTagName,
        $deployment->newTagComment,
        'origin/production'
    );
} else {
    $currentRemoteTags = $inst->git->getCurrentTags();

    $nothingToDeploy = true;
    if ($localInst->execOld('git log %s..origin/Live --pretty=oneline', reset($currentRemoteTags))) {
        if (trim($localInst->execOutput) !== '') {
            $nothingToDeploy = false;
        }
    }

    if ($nothingToDeploy) {
        deploymentDialog('nothingToDeploy', array('details' => $localInst->execOutput));
    }

    $localInst->git->createTag($deployment->newTagName, $deployment->newTagComment, 'origin/Live');
}