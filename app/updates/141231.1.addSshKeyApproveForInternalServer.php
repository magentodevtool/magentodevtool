<?php

$config = Config::getData();

if (!isset($config->ssh->strictHostKeyChecking->ipExceptionRegexp)) {
    Config::setNode('ssh/strictHostKeyChecking/ipExceptionRegexp', '10\..+');
    Config::save($config);
}
