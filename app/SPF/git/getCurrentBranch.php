<?php

# incspf exec

namespace SPF\git;

function getCurrentBranch()
{
    // define current branch
    $branches = trim(\SPF\exec('git branch'));
    $branches = explode("\n", $branches);
    foreach ($branches as $branch) {
        if (preg_match('~^\* (.*)$~', $branch, $ms)) {
            $currentBranch = $ms[1];
            break;
        }
    }
    if (
        !isset($currentBranch)
        || ($currentBranch == '(no branch)')
        || (strpos($currentBranch, 'detached from') !== false)
    ) {
        $currentBranch = false;
    }

    return $currentBranch;
}
