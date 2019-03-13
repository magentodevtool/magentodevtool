<?php

if ($inst->isCloud) {
    $git = $inst->getLocalInstallation()->git;
} else {
    $git = $inst->git;
}

$compareWith = $ARG->compareWith;
$filter = isset($ARG->filter) ? $ARG->filter : null; // can be not set for simple project
$diffs = array();

$branches = array_keys(array_filter((array)$ARG->branches));
if (!count($branches)) {
    return 'Nothing to compare';
}

$git->fetch();

$options = isset($ARG->options) ? $ARG->options : array();

foreach ($branches as $branch) {
    $diffs[$branch] = $git->getCleanBranchesDiff($compareWith, $branch, $options);
}

switch ($filter) {
    case 'upgrades':
        $fileRegexp = "~\/data\/|\/sql\/|\/Setup\/~";
        break;
    case 'depins':
        $fileRegexp = "~\/depins\/~";
        break;
    case 'depins_and_upgrades':
        $fileRegexp = "~\/depins\/|\/data\/|\/sql\/|\/Setup\/~";
        break;
    default:
        $fileRegexp = '';
}

if ($fileRegexp) {
    foreach ($diffs as $k => $v) {
        foreach ($v as $kk => $vv) {
            if (preg_match($fileRegexp, $vv->file) == 0) {
                unset($diffs[$k][$kk]);
            }
        }
    }
}

return $inst->form('git/codeReview/result', array('diffs' => $diffs));
