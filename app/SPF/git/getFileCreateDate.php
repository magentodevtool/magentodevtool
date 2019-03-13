<?php

# incspf exec

namespace SPF\git;

function getFileCreateDate($file)
{
    $date = \SPF\exec('git log --format=%%at %s | tail -1', $file);
    if ($date !== '') {
        return date('Y-m-d H:i:s O', $date);
    }
    return date('Y-m-d H:i:s O', filectime($file));
}
