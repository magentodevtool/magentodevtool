<?php

namespace Exception;

class Template extends \Exception
{

    public $template;
    public $vars;

    function __construct($template, $vars = array())
    {
        $this->template = $template;
        $this->vars = $vars;
    }

}
