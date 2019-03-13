<?php

# incspf exec

namespace SPF\docker;

function getDockerRunningContainers()
{
    try {
        $output = \SPF\exec('docker ps -f status=running --format \'{{.Names}}|{{.Image}}|{{.Ports }}|{{.ID}}\'');
        if ($output == '') {
            return false;
        }
        return $output;
    } catch (\Exception $e) {
        return false;
    }
}
