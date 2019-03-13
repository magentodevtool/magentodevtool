<?php

# incspf exec

namespace SPF\docker;

function recreateDatabases()
{
    try {
        // use getcwd because of __DIR__ is not what we need on local inst
        $docRoot = getcwd();

        // avoid symlinks affect
        $docRoot = realpath($docRoot) . '/';
        chdir($docRoot);

        return \SPF\exec(array(
            'docker-compose stop database',
            'sudo rm -rf ./.docker/mysql/data/',
            'docker-compose up -d',
        ));
    } catch (\Exception $e) {
        return false;
    }
}
