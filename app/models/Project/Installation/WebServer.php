<?php

namespace Project\Installation;

class WebServer
{

    /**
     * @var \Project\Installation $inst
     */
    protected $inst;
    public $type = null;

    /**
     * @return array
     */
    public function getDomains()
    {
        $domains = $this->getConfig() ? $this->getConfig()->domains : array();
        return array_unique($domains);
    }

    public function getConfig()
    {
        return false;
    }

    function getServiceName()
    {
        return preg_replace('/nginx-php-fpm/', 'nginx', $this->type);
    }

    function getLocalIp()
    {
        return getServiceLocalIp($this->getServiceName());
    }

}
