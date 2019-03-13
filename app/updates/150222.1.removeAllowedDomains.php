<?php

$config = Config::getData();
if (!isset($config->allowedDomains)) {
    return;
}
unset($config->allowedDomains);
Config::save($config);