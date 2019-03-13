<?php

namespace Project\Installation;

class Composer
{

    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    /** @var Composer\Application */
    protected $application;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    public function run($args, $dir = null)
    {
        return $this->getApplication()->runComposerCommand($args, $dir);
    }

    public function getApplication()
    {
        if (empty($this->application)) {
            $this->application = new Composer\Application(
                $this->inst,
                $this->inst->_appRoot . 'composer.json'
            );
        }
        return $this->application;
    }

}
