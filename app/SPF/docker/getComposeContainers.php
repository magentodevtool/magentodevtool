<?php

# incspf exec

namespace SPF\docker;

function getComposeContainers()
{
    try {
        $output = \SPF\exec('docker-compose ps');
        if ($output == '') {
            return false;
        }
        return $output;
    } catch (\Exception $e) {
        return false;
    }
}
