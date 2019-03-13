<?php

if ($deployment->type === 'production') {

    // check if new tag already exists remotely
    if (!$inst->isCloud) {
        if (!$tags = $inst->git->getTags()) {
            error('Can\'t check remote tags');
        }
        if (isset($tags[$deployment->newTagName])) {
            error('Tag "' . $deployment->newTagName . '" already exists on ' . $inst->name . ', please remove');
        }
    }

    // check if new tag already exists locally
    if (!$tags = $localInst->git->getTags()) {
        error('Can\'t check local tags');
    }
    if (isset($tags[$deployment->newTagName])) {
        error('Tag "' . $deployment->newTagName . '" already exists on ' . $localInst->name . ', please remove');
    }

    // validate tag name
    if ($localInst->execOld('git tag %s -a -m %s', $deployment->newTagName, $deployment->newTagComment)) {
        $localInst->execOld('git tag -d %s', $deployment->newTagName);
    } else {
        error("Git doesn't support such tag name or comment:\n" . $localInst->execOutput);
    }

}

// validate fetch, exception will be thrown if error
$localInst->git->fetch();
