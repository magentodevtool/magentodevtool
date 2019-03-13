<?php

# incspf exec

namespace SPF\docker;

function getComposeProjectName()
{
    if (!file_exists('.env')) {
        return false;
    }

    $env = file_get_contents('.env');
    if (!preg_match('~[^;#]\s*COMPOSE_PROJECT_NAME\s*=\s*([^\s]+)~ism', $env, $matches)) {
        return false;
    }

    return $matches[1];

}
