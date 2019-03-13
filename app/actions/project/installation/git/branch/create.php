<?php

$branchName = $ARG;
$remoteBranches = $inst->git->getRemoteBranches();

if (isset($remoteBranches[$branchName])) {
    error('Branch already exists');
}

$commands = array(
    'git fetch',
    'git branch %s origin/master',
);

$inst->exec($commands, $branchName);

try {
    $inst->exec('git push origin %s:%s', 'refs/heads/' . $branchName, 'refs/heads/' . $branchName);
} catch (Exception $pushException) {
}

// Clean local branch so that:
// - branch will be cleaned if push failed
// - branch upstream will be re-created correctly when switch on this branch
$inst->exec('git branch -d %s', $branchName);

if (isset($pushException)) {
    throw $pushException;
}
