<?php

$currentBranch = $inst->git->getCurrentBranch();
if (!$inst->execOld('git push origin %s', $currentBranch)) {
    error($inst->execOutput);
}