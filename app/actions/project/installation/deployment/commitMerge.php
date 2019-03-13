<?php

$wereUnmerged = $deployment->unmergedFiles;

$conflictRx = array(
    '~^<<<<<<< ~',
    '~\n<<<<<<< ~',
    '~\n=======\n~',
    '~\n>>>>>>> ~',
);

$unmergedFiles = array();
foreach ($wereUnmerged as $wasUnmerged) {
    switch ($wasUnmerged->state) {
        case 'UU':
            $file = file_get_contents($localInst->folder . $wasUnmerged->file);
            foreach ($conflictRx as $rx) {
                if (preg_match($rx, $file)) {
                    $unmergedFiles[$wasUnmerged->file] = 1;
                }
            }
            break;
        default:
            $localInst->execOld('cd %s && git status --porcelain %s', $localInst->folder, $wasUnmerged->file);
            $fileState = substr(trim($localInst->execOutput), 0, 2);
            if (in_array($fileState, array('DD', 'AU', 'UD', 'UA', 'DU', 'AA', 'UU'))) {
                $unmergedFiles[$wasUnmerged->file] = 1;
            }
            break;
    }
}
$unmergedFiles = array_keys($unmergedFiles);

if (count($unmergedFiles)) {
    deploymentDialog('stillConflicts', array('unmergedFiles' => $unmergedFiles));
}

if (!$localInst->execOld(array('git add .', 'git commit --no-edit'))) {
    deploymentDialog('failedToCommitMerge', array('details' => $localInst->execOutput));
}