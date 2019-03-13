<?php

$filesToReset = $ARG;

foreach ($filesToReset as $fileToReset) {

    // all 3 commands can return error but its ok, separate them with ";"
    $inst->execOld(array('git reset -- %s; rm %s; git checkout %s'), $fileToReset, $fileToReset, $fileToReset);

    // check for error e.g. permission denied
    $inst->execOld('git status --porcelain %s', $fileToReset);
    if (trim($inst->execOutput) !== '') {
        error('Failed, check permissions');
    }

}