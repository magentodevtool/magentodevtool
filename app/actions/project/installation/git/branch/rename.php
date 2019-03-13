<?php

$currentName = trim($ARG->currentName);
$newName = trim($ARG->newName);

if ($newName === '') {
    error('Invalid new branch name');
}

$remoteBranches = $inst->git->getRemoteBranches();

if (isset($remoteBranches[$newName])) {
    error('Branch already exists');
}

$inst->exec(
    array(
        'git push origin %s:%s',
        'git push origin :%s',
        'git checkout %s',
        'git branch -D %s'
    ),
    $currentName, $newName, $currentName, $newName, $currentName
);
