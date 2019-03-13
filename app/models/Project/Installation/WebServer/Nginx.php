<?php

namespace Project\Installation\WebServer;

class Nginx extends \Project\Installation\WebServer
{

    public $type = 'nginx-php-fpm';

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    function getConfig()
    {
        $config = new Nginx\Config($this->inst);

        return isset($config->domains) ? $config : false;
    }

}
