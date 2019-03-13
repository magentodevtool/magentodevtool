<?php

namespace Project\Installation\WebServer;

class Apache extends \Project\Installation\WebServer
{

    public $type = 'apache';

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    public function getConfig()
    {
        $config = new Apache\Config($this->inst);
        if (isset($config->ServerName)) {
            return $config;
        }
        return false;
    }

}
