<?php

# incspf exec

namespace SPF\docker;

function dockerCleanup()
{
    try {
        return \SPF\exec('docker rm -v $(docker ps -a -q -f status=exited -f status=created)') . PHP_EOL .
            \SPF\exec('docker rmi $(docker images -f "dangling=true" -q)') . PHP_EOL .
            \SPF\exec('docker volume rm $(docker volume ls -qf dangling=true)');
    } catch (\Exception $e) {
        return false;
    }
}
