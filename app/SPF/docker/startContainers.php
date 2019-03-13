<?php

# incspf exec

namespace SPF\docker;

function startContainers($recreate = false, $build = false)
{
    try {
        return \SPF\exec('docker-compose up -d --no-color'
            . ($recreate ? ' --force-recreate' : '')
            . ' ' . ($build ? '--build' : '--no-build'));
    } catch (\Exception $e) {
        return false;
    }
}
