<?php

namespace Project\Installation;

class Generation
{
    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    function __construct($inst)
    {
        $this->inst = $inst;
        $this->scss = new Generation\Scss($inst);
        $this->composer = new Generation\Composer($inst);
    }
}