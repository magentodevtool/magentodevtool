<?php

namespace Project\Installation\Magento;

class Module
{
    /** @var \Project\Installation $inst */
    protected $inst;
    /** @var Module\Export $export */
    public $export;
    /** @var Module\Setup $setup */
    public $setup;

    public function __construct($inst)
    {
        $this->inst = $inst;
        $this->export = new Module\Export($inst);
        $this->setup = new Module\Setup($inst);
    }

}
