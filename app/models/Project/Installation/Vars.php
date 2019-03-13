<?php

namespace Project\Installation;

class Vars
{

    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    function __construct($inst)
    {
        $this->inst = $inst;
    }

    function get($key)
    {
        return \Vars::get(LDAP_USER, $this->inst->project->name, $this->inst->name, $key);
    }

    function set($key, $value)
    {
        \Vars::set(LDAP_USER, $this->inst->project->name, $this->inst->name, $key, $value);
    }

    function delete($key)
    {
        \Vars::delete(LDAP_USER, $this->inst->project->name, $this->inst->name, $key);
    }

}
