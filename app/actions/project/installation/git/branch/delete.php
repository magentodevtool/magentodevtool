<?php

$branchName = $ARG;

if (!$inst->execOld('git push origin :%s', 'refs/heads/' . $branchName)) {
    error($inst->execOutput);
}

if ($inst->git->getCurrentBranch() === $branchName) {
    $inst->execOld('git checkout master');
}

$inst->execOld('git branch -D %s', $branchName);