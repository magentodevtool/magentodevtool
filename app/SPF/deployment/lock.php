<?php

#incspf error

namespace SPF\deployment;

function lock()
{
    // empty function in order to avoid Fatal error: Cannot redeclare class SPF\deployment\Lock
}

class Lock
{
    protected $file;
    protected $info;
    const EXPIRE_SECONDS = 20;

    function __construct()
    {
        global $instInfo;
        if ($instInfo->type === 'remote') {
            $this->file = 'var/deployment.lock';
        } else {
            $lockDir = DATA_DIR_INT_LOCK . 'deployment/';
            if (!is_dir($lockDir)) {
                mkdir($lockDir, 0777, true);
            }
            $this->file = $lockDir . sha1($instInfo->folder) . '.lock';
        }
    }

    public function capture($for)
    {
        $this->cleanExpired();

        $dir = dirname($this->file);
        if (!is_writable($dir)) {
            \SPF\error("Lock directory \"$dir\" isn't writable");
        }

        if ($handle = @fopen($this->file, 'x')) {
            fclose($handle);

            $info = (object)array(
                'user' => LDAP_USER,
                'for' => $for,
                'hash' => sha1(microtime()),
            );

            file_put_contents($this->file, json_encode((object)$info));

            return $info->hash;
        }

        return false;
    }

    protected function cleanExpired()
    {
        if (file_exists($this->file)) {
            if ((time() - filemtime($this->file)) > static::EXPIRE_SECONDS) {
                @unlink($this->file);
            }
        }
    }

    public function isWritable($hash)
    {
        return ($info = $this->getInfo()) && ($info->hash === $hash) && ($info->user === LDAP_USER);
    }

    public function release($hash)
    {
        if (!$this->isWritable($hash)) {
            return false;
        }
        @unlink($this->file);
        return true;
    }

    public function prolong($hash)
    {
        if (!$this->isWritable($hash)) {
            return false;
        }
        return touch($this->file);
    }

    public function getInfo()
    {
        $this->cleanExpired();

        if (!is_null($this->info)) {
            return $this->info;
        }
        if (!file_exists($this->file)) {
            return false;
        }
        $info = file_get_contents($this->file);
        $info = json_decode($info);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        $this->info = $info;
        return $this->info;
    }

}
