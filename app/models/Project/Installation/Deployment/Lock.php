<?php

namespace Project\Installation\Deployment;

class Lock
{
    /**
     * @var \Project\Installation $inst
     */
    protected $inst;
    protected $localInst;
    protected $info;

    function __construct($inst)
    {
        $this->inst = $inst;
    }

    protected function getLocalInst()
    {
        if (!is_null($this->localInst)) {
            return $this->localInst;
        }
        return $this->localInst = $this->inst->deployment->localInstallation->get();
    }

    public function capture()
    {
        if ($this->inst->isCloud) {
            // environment is down during rebuild, needs to be implemented differently
            $remoteHash = 'dummyHash';
        } else {
            $remoteHash = $this->inst->spf('deployment/lock/capture', $this->inst->name);
        }
        if (!$remoteHash) {
            return false;
        }

        $localHash = $this->getLocalInst()->spf('deployment/lock/capture', $this->inst->name);
        if (!$localHash) {
            $this->inst->spf('deployment/lock/release', $remoteHash);
            return false;
        }
        return (object)[
            'remote' => $remoteHash,
            'local' => $localHash,
        ];
    }

    public function isWritable($hash)
    {
        try {
            if ($this->inst->isCloud) {
                // environment is down during rebuild, needs to be implemented differently
                $remoteIsWritable = true;
            } else {
                $remoteIsWritable = $this->inst->spf('deployment/lock/isWritable', $hash->remote);
            }
            $localIsWritable = $this->getLocalInst()->spf('deployment/lock/isWritable', $hash->local);
        } catch (\Exception $e) {
            return false;
        }
        return $remoteIsWritable && $localIsWritable;
    }

    public function release($hash)
    {
        if ($this->inst->isCloud) {
            // environment is down during rebuild, needs to be implemented differently
            $remoteRelease = true;
        } else {
            $remoteRelease = $this->inst->spf('deployment/lock/release', $hash->remote);
        }
        $localRelease = $this->getLocalInst()->spf('deployment/lock/release', $hash->local);
        return $remoteRelease && $localRelease;
    }

    public function prolong($hash)
    {
        if ($this->inst->isCloud) {
            $remoteProlong = true;
        } else {
            $remoteProlong = $this->inst->spf('deployment/lock/prolong', $hash->remote);
        }
        $localProlong = $this->getLocalInst()->spf('deployment/lock/prolong', $hash->local);
        return $remoteProlong && $localProlong;
    }

    public function getInfo()
    {
        if (!is_null($this->info)) {
            return $this->info;
        }
        $info = $this->getLocalInst()->spf('deployment/lock/getInfo');
        if (!$info) {
            $info = $this->inst->spf('deployment/lock/getInfo');
        }
        $this->info = $info;
        return $this->info;
    }

}
