<?php

class Config extends Json
{

    protected static $file = 'config.json';

    static function getData()
    {

        $config = parent::getData();

        if (!isset($config->workspace)) {
            $config->workspace = '';
            parent::save($config);
        }

        return $config;

    }

}
