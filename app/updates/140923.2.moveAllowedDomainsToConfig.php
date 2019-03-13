<?php

if (!is_readable(DATA_DIR . 'allowedDomains')) {
    return;
}

$config = Config::getData();

$domainsList = file_get_contents(DATA_DIR . 'allowedDomains');
$domainsList = trim(preg_replace('~\s+~', ',', $domainsList), ',');
$domainsList = explode(',', $domainsList);
$config->allowedDomains = $domainsList;

Config::save($config);

unlink(DATA_DIR . 'allowedDomains');
