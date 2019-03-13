<?php

namespace Exception;

class Bash extends Template
{

    function __construct($vars = array())
    {
        parent::__construct('exception/bash', $vars);
        $this->message = $vars['output'];
    }

}
