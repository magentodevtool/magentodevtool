<?php

# incspf exec
# incspf git/getDir

namespace SPF\git;

function getFileLastCommit($file)
{
    // go to repo root
    $cwdSave = getcwd();
    chdir(getDir() . '..');

    try {
        $output = \SPF\exec('git log -1 %s', $file);
    } catch (\Exception $e) {
    }

    // restore cwd
    chdir($cwdSave);
    if (isset($e)) {
        throw $e;
    }

    $commit = array();
    foreach (explode("\n", $output) as $line) {
        if (preg_match('~^commit\s+(.+)$~', $line, $ms)) {
            $commit['hash'] = $ms[1];
        } elseif (preg_match('~^Author:\s+(.+)$~', $line, $ms)) {
            $commit['author']['string'] = $ms[1];
            if (preg_match('~^(.+) <(.+)>$~', $commit['author']['string'], $ms)) {
                $commit['author']['name'] = $ms[1];
                $commit['author']['email'] = $ms[2];
            }
        } elseif (preg_match('~^Date:\s+(.+)$~', $line, $ms)) {
            $commit['date'] = $ms[1];
        } elseif ($line === "") {
            $commit['comment'] = "";
        } elseif (preg_match('~^    (.+)$~', $line, $ms)) {
            $commit['comment'] .= $ms[1];
        }
    }

    return $commit;
}
