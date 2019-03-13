<?php

# incspf exec

namespace SPF\git;

function getBranchesDiffLog($branch1, $branch2, $allRemote = true, $excludeMerges = true)
{

    if ($allRemote) {
        $branch1 = 'origin/' . $branch1;
        $branch2 = 'origin/' . $branch2;
    }

    $gitDiffLogText = \SPF\exec('git log %s..%s --parents', $branch1, $branch2);
    $gitDiffLog = explode("\n", $gitDiffLogText);

    $commits = array();

    foreach ($gitDiffLog as $i => $line) {

        if (!preg_match('~^commit ([0-9a-f]+)(.*)$~', $line, $ms)) {
            continue;
        }

        $parents = trim($ms[2]) === '' ? array() : explode(' ', trim($ms[2]));
        $isMerge = count($parents) > 1;

        if ($excludeMerges && $isMerge) {
            continue;
        }

        $commit = array(
            'hash' => $ms[1],
            'isMerge' => $isMerge,
            'comment' => '',
        );

        unset($gitDiffLog[$i]);

        // collect comment lines
        $j = $i;
        while (++$j) {
            if (!isset($gitDiffLog[$j])) {
                break;
            }
            $nextLine = $gitDiffLog[$j];
            if (strpos($nextLine, 'commit ') === 0) {
                break;
            }
            if (strpos($nextLine, '    ') === 0) {
                $commit['comment'] .= "\n" . substr($nextLine, 4);
            }
            unset($gitDiffLog[$j]);
        }
        $commit['comment'] = trim($commit['comment']);

        $commits[] = $commit;

    }

    return $commits;

}
