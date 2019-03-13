<?php
return array(
    'backend' =>
        array(
            'frontName' => 'admin_16r8ug',
        ),
    'crypt' =>
        array(
            'key' => '9b168aec03b801c01a4d97a8d39a9dbb',
        ),
    'session' =>
        array(
            'save' => 'files',
        ),
    'db' =>
        array(
            'table_prefix' => '',
            'connection' =>
                array(
                    'default' =>
                        array(
                            'host' => 'localhost',
                            'dbname' => '',
                            'username' => 'root',
                            'password' => 'abcABC123',
                            'model' => 'mysql4',
                            'engine' => 'innodb',
                            'initStatements' => 'SET NAMES utf8;',
                            'active' => '1',
                            'port' => '3306',
                        ),
                ),
        ),
    'resource' =>
        array(
            'default_setup' =>
                array(
                    'connection' => 'default',
                ),
        ),
    'x-frame-options' => 'SAMEORIGIN',
    'MAGE_MODE' => 'production',
    'cache_types' =>
        array(
            'config' => 1,
            'layout' => 1,
            'block_html' => 1,
            'collections' => 1,
            'reflection' => 1,
            'db_ddl' => 1,
            'eav' => 1,
            'config_integration' => 1,
            'config_integration_api' => 1,
            'full_page' => 1,
            'translate' => 1,
            'config_webservice' => 1,
            'compiled_config' => 1,
        ),
    'install' =>
        array(
            'date' => 'Wed, 15 Jun 2016 12:24:47 +0000',
        ),
);
