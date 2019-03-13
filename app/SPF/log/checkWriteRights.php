<?php

namespace SPF\log;

function checkWriteRights()
{
    $writableDirs = array('var/log', 'var/report', 'var/archive');
    if (!is_dir('var/archive')) {
        $writableDirs[] = 'var';
    }
    foreach ($writableDirs as $writableDir) {
        if (is_dir($writableDir) && (!is_writable($writableDir) || !is_executable($writableDir))) {
            \SPF\error("Directory $writableDir is not writable");
        }
    }
}
