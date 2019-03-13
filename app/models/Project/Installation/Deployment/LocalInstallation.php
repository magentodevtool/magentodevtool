<?php

namespace Project\Installation\Deployment;

class LocalInstallation
{
    /**
     * @var \Project\Installation $inst
     */
    protected $inst;
    protected $localInstallationCache;

    function __construct($inst)
    {
        $this->inst = $inst;
    }

    function get()
    {
        if (!is_null($this->localInstallationCache)) {
            return $this->localInstallationCache;
        }
        $this->localInstallationCache = $this->getDirectly();
        return $this->localInstallationCache;
    }

    protected function getDirectly()
    {
        $localInst = \Project::getLocalInstallation($this->inst->source, $this->inst->project->name);

        if (!$localInst) {
            return false;
        }

        if (\Config::getNode('isCentralized')) {
            return $localInst;
        }

        // Local Devtool should use separate deployment environment instead of development env
        $localInst = new \Project\Installation((object)[
            'type' => 'local',
            'source' => 'local',
            'folder' => str_replace('{workspace}', '{workspace}/deployment', $localInst->origData->folder),
            'name' => $localInst->name . '-Deployment',
            'project' => $localInst->project,
        ]);

        return $localInst;
    }

    public function getDefaultDbName()
    {
        $key = $this->get()->project->name . '_' . $this->get()->name;
        $key = preg_replace('~-?deployment$~i', '', $key);
        $dbName = preg_replace('~[^a-z0-9_]~ism', '_', $key);
        $dbName = preg_replace('~_+~', '_', $dbName);
        return 'deployment_' . strtolower($dbName);
    }

}
