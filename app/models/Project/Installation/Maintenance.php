<?php

namespace Project\Installation;

class Maintenance
{
    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    public function turnOn($allowedIPs)
    {
        $this->inst->spf('maintenance/turnOn', $allowedIPs);
    }

    public function turnOff()
    {
        $this->inst->spf('maintenance/turnOff');
    }
}
