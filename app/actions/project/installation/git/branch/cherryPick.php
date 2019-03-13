<?php

$currentBranch = $inst->git->getCurrentBranch();
$selectedHashes = $ARG->selectedHashes;
$dontCommit = $ARG->dontCommit;
$result = array(
    'commands' => array(),
    'dontCommit' => $dontCommit,
);

if (empty($selectedHashes) || !is_array($selectedHashes)) {
    error('You didn\'t select any commits!');
}
if (!$inst->execOld('git status --porcelain')) {
    error('Failed to check git status: ' . $inst->execOutput);
}
if (trim($inst->execOutput) !== '') {
    error('Git status must be clear before cherry pick');
}
if (!$inst->execOld('git pull')) {
    error($inst->execOutput);
}

$branchesCommits = $inst->git->getCherryPickCommits($currentBranch);

// filter selected commits to make sure order is the same as shown in the form
$sortedSelectedHashes = array();
foreach ($branchesCommits as $branchCommits) {
    foreach ($branchCommits as $branchCommit) {
        if (in_array($branchCommit['hash'], $selectedHashes)) {
            $sortedSelectedHashes[] = $branchCommit['hash'];
        }
    }
}
// sort hashes from older to newest
$sortedSelectedHashes = array_reverse($sortedSelectedHashes);

foreach ($sortedSelectedHashes as $key => $hash) {
    $result['commands'][] = array(
        'cmd' => cmd($dontCommit ? 'git cherry-pick -n %s' : 'git cherry-pick %s', $hash),
        'status' => 'Didn\'t run',
        'error' => '',
    );
}

foreach ($result['commands'] as &$command) {
    if (!$inst->execOld($command['cmd'])) {
        $command['status'] = 'Fail';
        $command['error'] = $inst->execOutput;
        $inst->execOld(array('git reset --hard %s'), 'origin/' . $currentBranch);
        return $inst->form('git/branch/cherryPick/result', (array)$result);
    }
    $command['status'] = 'Success';
}

if ($dontCommit) {
    return $inst->form('git/branch/cherryPick/result', (array)$result);
}

$cmdPush = cmd('git push origin %s', $currentBranch);
$result['commands']['push'] = array(
    'cmd' => $cmdPush,
    'status' => 'Ran',
    'error' => '',
);

if (!$inst->execOld($cmdPush)) {
    $result['commands']['push']['status'] = 'Fail';
    $result['commands']['push']['error'] = $inst->execOutput;
}

return $inst->form('git/branch/cherryPick/result', (array)$result);
