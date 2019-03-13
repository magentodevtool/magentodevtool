<?php

$currentBranch = $inst->git->getCurrentBranch();

foreach ($ARG as $branch) {
    if ($branch === $currentBranch) {
        $inst->execOld('git checkout master');
    }
    if (!$inst->execOld('git branch -D %s', $branch)) {
        error('Failed on: ' . cmd('git branch -D %s', $branch));
    }
}