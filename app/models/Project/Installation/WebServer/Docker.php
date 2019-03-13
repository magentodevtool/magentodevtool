<?php

namespace Project\Installation\WebServer;

use Project\Installation\WebServer;

class Docker extends WebServer
{

    public $type = 'docker';

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    public function getConfig()
    {
        $config = new Docker\Config($this->inst);

        return isset($config->domains) ? $config : false;
    }
}
