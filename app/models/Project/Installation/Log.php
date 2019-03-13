<?php

namespace Project\Installation;

class Log
{
    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    public function rotate()
    {
        $this->inst->spf('log/rotate');
    }

}
