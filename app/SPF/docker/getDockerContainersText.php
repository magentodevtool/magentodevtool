<?php

# incspf exec

namespace SPF\docker;

function getDockerContainersText()
{
    try {
        $output = \SPF\exec('docker ps');
        if ($output == '') {
            return false;
        }
        return $output;
    } catch (\Exception $e) {
        return false;
    }
}
