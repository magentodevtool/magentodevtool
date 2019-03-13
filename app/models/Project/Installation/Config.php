<?php

namespace Project\Installation;

class Config
{
    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    function backup($comment)
    {
        return $this->inst->spf('config/backup', $comment);
    }
}
