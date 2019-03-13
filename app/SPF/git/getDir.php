<?php

namespace SPF\git;

function getDir()
{
    $cwd = getcwd();
    foreach (array("$cwd/.git", "$cwd/../.git") as $dir) {
        if (is_dir($dir)) {
            return realpath($dir) . '/';
        }
    }
    \SPF\error('.git dir not found');
}
