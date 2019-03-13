<?php

$comment = trim($ARG->comment);
if (empty($comment)) {
    error('Please fill-in comment');
}

if (!$currentBranch = $inst->git->getCurrentBranch()) {
    error('Branch required');
}

$remoteBranches = $inst->git->getRemoteBranches();

if (isset($remoteBranches[$currentBranch])) {
    $currentBranchAheadItself = count($inst->git->getBranchesDiffLog(
        'origin/' . $currentBranch,
        $currentBranch,
        false,
        false
    ));
    // if no conflict on pull
    if ($currentBranchAheadItself === 0) {
        // try to pull in order to keep git tree more clear
        $inst->execOld('git pull');
    }
}

if (!$inst->execOld('git commit -m %s', $comment)) {
    error($inst->execOutput);
}

$pushError = false;

if ($ARG->doPush) {
    if (isset($remoteBranches[$currentBranch])) {
        if (!$inst->execOld('git pull origin %s', $currentBranch)) {
            $pushError = $inst->execOutput;
            $inst->execOld('git merge --abort');
        } elseif (!$inst->execOld('git push origin %s', $currentBranch)) {
            $pushError = $inst->execOutput;
        }
    } else {
        // create remote branch if local branch has been pushed
        if (!$inst->execOld('git push origin %s:%s', $currentBranch, $currentBranch)) {
            $pushError = $inst->execOutput;
        }
    }
}

return array(
    'pushError' => $pushError
);
