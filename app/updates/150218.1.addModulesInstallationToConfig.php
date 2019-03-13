<?php

$config = Config::getData();
$config->setNode(
    'magento/modulesInstallation',
    array(
        'source' => 'remote',
        'project' => 'Company Modules',
        'name' => 'Local'
    )
);
Config::save($config);
