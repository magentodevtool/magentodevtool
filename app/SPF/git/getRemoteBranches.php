<?php

# incspf git/getDir
# incspf git/getBranchesDiffLog

namespace SPF\git;

function getRemoteBranches($alphaName = 'Alpha', $betaName = 'Beta')
{
    $info = \SPF\exec('git branch -r');
    $info = preg_replace('~ +origin/~', '', $info);
    $info = trim(preg_replace('~\nHEAD ->[^\n]+\n~', "\n", "\n" . $info));
    $names = explode("\n", $info);
    $branches = array();
    foreach ($names as $name) {
        $data = array('name' => $name, 'ahead' => array(), 'behind' => array());
        $data['ahead']['master'] = getBranchesDiffLog('master', $name);
        $data['behind']['master'] = getBranchesDiffLog($name, 'master');
        if (in_array($alphaName, $names)) {
            $data['ahead'][$alphaName] = getBranchesDiffLog($alphaName, $name);
        }
        if (in_array($betaName, $names)) {
            $data['ahead'][$betaName] = getBranchesDiffLog($betaName, $name);
        }
        $branches[$name] = $data;
    }
    return $branches;
}
