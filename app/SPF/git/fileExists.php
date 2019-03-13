<?php

# incspf exec
# incspf git/getDir

namespace SPF\git;

function fileExists($file, $branch = 'master')
{
    // appPath is needed for "git show origin:master" because related path using "./" isn't supported for git version 1.7.1
    $appPath = str_replace(str_replace('.git/', '', getDir()), '', getcwd() . '/');
    $filePath = $appPath . $file;

    try {
        \SPF\exec('git show %s:%s', $branch, $filePath);
    } catch (\Exception $e) {
        // likely, file doesn't exists.
        return false;
    }

    return true;
}
