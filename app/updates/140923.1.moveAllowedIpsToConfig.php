<?php

if (!is_readable(DATA_DIR . 'allowedIPs')) {
    return;
}

$config = Config::getData();

$ipsList = file_get_contents(DATA_DIR . 'allowedIPs');
preg_match_all('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $ipsList, $ms);
$ipsList = $ms[0];
$config->allowedIpAddresses = $ipsList;

Config::save($config);

unlink(DATA_DIR . 'allowedIPs');
