<?php

namespace Project\Installation;

/**
 * Class Console
 * @package Project\Installation
 *
 * @method \Project\Installation inst()
 */
trait Console
{
    /**
     * @deprecated because is used only by deprecated function execOld
     *
     * @var $execOutput deprecated.
     */
    public $execOutput = null;
    protected $sshConnection;

    /**
     * Execute shell command.
     */
    public function exec()
    {
        $args = func_get_args();
        $cmd = $this->prepareExecCmd($args);

        // pick limits are 131072 : 34707
        $commandLengthLimit = $this->inst()->type === 'local' ? 130000 : 34000;
        if (strlen($cmd) >= $commandLengthLimit) {
            error("ERROR: Bash command is too big");
        }

        if ($this->inst()->type === 'remote') {
            $this->getSshConnection()->exec($cmd, $o, $r);
        } else {
            exec($cmd, $o, $r);
        }

        $output = utf8_encode(implode("\n", $o));

        if ($r !== 0) {
            $this->execError($args, $output, $r);
        }

        return $output;
    }

    public function execTimeout($cmd, $args, $timeout)
    {
        if (is_int($timeout)) {
            $timeout .= 's';
        }
        $commands = (array)$cmd;
        foreach ($commands as &$command) {
            $command = 'timeout ' . escapeshellarg($timeout) . ' ' . $command;
        }
        $execArgs = array_merge($commands, $args);
        try {
            return call_user_func_array([$this, 'exec'], $execArgs);
        } catch (\Exception $e) {
            if ($e instanceof \Exception\Bash && $e->vars['errorCode'] === 124) {
                $eVars = $e->vars;
                $eVars['output'] = 'Timeout ' . $timeout;
                error_bash($eVars);
            }
            throw $e;
        }
    }

    protected function getSshConnection()
    {
        if (is_null($this->sshConnection)) {
            $ssh = $this->getSsh()->connect();
            $ssh->forwardAgent();
            $this->sshConnection = $ssh;
        }
        return $this->sshConnection;
    }

    function prepareExecCmd($args)
    {
        $cmd = (array)array_shift($args);
        if ($this->inst()->type === 'remote') {
            array_unshift($cmd, 'umask 002');
        }
        array_unshift($cmd, 'cd ' . escapeshellarg($this->_appRoot));
        array_unshift($args, $cmd);
        return call_user_func_array('cmd', $args);
    }

    function prepareExecCmdSimple($args)
    {
        cmd_stderror_redirection('');
        $cmd = call_user_func_array('cmd', $args);
        cmd_stderror_redirection_reset();
        return $cmd;
    }

    function execError($args, $output, $errorCode)
    {
        error_bash(
            array(
                'installationName' => $this->inst()->name,
                'projectName' => $this->inst()->project->name,
                'command' => $this->prepareExecCmdSimple($args),
                'fullCommand' => $this->prepareExecCmd($args),
                'output' => $output,
                'errorCode' => $errorCode,
            )
        );
    }

    /**
     * Execute shell command.
     *
     * @deprecated please use 'exec' method.
     *
     * @return boolean
     */
    public function execOld()
    {
        $this->execOutput = null;
        $args = func_get_args();
        $cmd = (array)array_shift($args);
        if ($this->inst()->type === 'remote') {
            array_unshift($cmd, 'umask 002');
        }
        array_unshift($cmd, 'cd ' . escapeshellarg($this->_appRoot));
        array_unshift($args, $cmd);
        $cmd = call_user_func_array('cmd', $args);

        // pick limits are 131072 : 34707
        $commandLengthLimit = $this->inst()->type === 'local' ? 130000 : 34000;
        if (strlen($cmd) >= $commandLengthLimit) {
            $this->execOutput = "ERROR: Bash command is too big";
            return false;
        }

        if ($this->inst()->type === 'remote') {
            $this->getSshConnection()->exec($cmd, $o, $r);
        } else {
            exec($cmd, $o, $r);
        }
        $this->execOutput = implode("\n", $o);

        return $r === 0;
    }

    public function uploadFile($localFile, $remoteFile)
    {
        $remoteFile = $remoteFile{0} === '/' ? $remoteFile : $this->inst()->_appRoot . $remoteFile;
        $ssh = $this->getSsh();
        return $ssh->uploadFile($localFile, $remoteFile);
    }

    public function downloadFile($remoteFile, $localFile)
    {
        $remoteFile = $remoteFile{0} === '/' ? $remoteFile : $this->inst()->_appRoot . $remoteFile;
        $ssh = $this->getSsh();
        return $ssh->downloadFile($remoteFile, $localFile);
    }

    public function execInDockerService()
    {
        $args = func_get_args();
        $service = array_shift($args);
        $user = array_shift($args);
        $commands = (array)$args[0];
        $commands = array_map(
            function ($cmd) use ($service, $user) {
                if ($user !== '') {
                    $user = "--user $user";
                }
                return "docker-compose exec -T $user $service $cmd";
            },
            $commands
        );
        $args[0] = $commands;
        return call_user_func_array([$this, 'exec'], $args);
    }

    public function getSshIdentityFile()
    {
        return !empty($this->identityFile) ? $this->identityFile : null;
    }

    public function getSshPort()
    {
        return $port = !empty($this->port) ? $this->port : 22;
    }

    public function getSsh()
    {
        return new \SSH($this->login, $this->host, $this->getSshPort(), $this->getSshIdentityFile());
    }

}
