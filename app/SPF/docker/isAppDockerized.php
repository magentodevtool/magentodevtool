<?php

namespace SPF\docker;

function isAppDockerized()
{
    return file_exists('docker-compose.yml') && file_exists('app/etc/docker-compose.local.yml');
}
