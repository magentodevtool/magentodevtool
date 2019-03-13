<?php

class SSH
{

    protected $connectionKey;
    protected $login;
    protected $hostIp;
    protected $port;
    protected $identityFile;
    protected $env = array();
    protected $connection;

    function __construct($login, $host, $port = 22, $identityFile = null)
    {
        $this->login = $login;
        $this->hostIp = resolveIp($host);
        $this->port = $port;
        $this->identityFile = $identityFile;
    }

    public function connect()
    {
        static $connections = array();
        $login = $this->login;
        $hostIp = $this->hostIp;

        // force to use IP because of it greatly accelerate connection especially in case of byte hosting
        $connectionKey = $login . '@' . $hostIp;

        if (!isset($connections[$connectionKey])) {
            if (!$connection = ssh2_connect($hostIp, $this->port)) {
                error("SSH2: connection failed");
            }
            $privateKeyFile = $this->getPrivateKey();
            $privateKeyContent = file_get_contents($privateKeyFile);
            if (strpos($privateKeyContent, 'ENCRYPTED')) {
                error("SSH2: encrypted private key is not supported");
            }
            if (!ssh2_auth_pubkey_file($connection, $login, $this->getPublicKey(), $privateKeyFile)) {
                error("SSH2: authentication failed");
            }

            $connections[$connectionKey] = $connection;
        }

        $this->connection = $connections[$connectionKey];

        return $this;
    }

    public function forwardAgent()
    {
        $agent = $this->runAgent();
        $this->env['SSH_AUTH_SOCK'] = $agent->authSock;
        if ($agent->hasBeenCreated) {
            $this->addKeyToAgent();
        }
    }

    protected function runAgent()
    {
        $agentKey = $this->login . '@' . $this->hostIp . '/ssh-agent';
        $agent = Vars::get('', '', '', $agentKey);
        if (!is_object($agent)) {
            $agent = new stdClass();
            $agent->pid = 0;
        }

        if ($agent->pid) {
            $cmd = shellescapef("if ! kill -0 %s > /dev/null 2>&1; then ssh-agent; fi", $agent->pid);
        } else {
            $cmd = "ssh-agent";
        }

        $this->exec($cmd, $o, $r);
        if ($r !== 0) {
            error("SSH: Agent forwarding failed");
        }

        $hasBeenCreated = count($o) > 1 && strpos($o[0], 'SSH_AUTH_SOCK=') === 0;
        if ($hasBeenCreated) {
            preg_match('~^SSH_AUTH_SOCK=(.+); export SSH_AUTH_SOCK;$~', $o[0], $ms);
            $agent->authSock = $ms[1];
            preg_match('~^SSH_AGENT_PID=(.+); export SSH_AGENT_PID;$~', $o[1], $ms);
            $agent->pid = $ms[1];
            Vars::set('', '', '', $agentKey, $agent);
        }

        if (!$agent->pid) {
            error('SSH: Agent forwarding failed, ssh-agent pid wasn\'t found');
        }

        $agent->hasBeenCreated = $hasBeenCreated;

        return $agent;
    }

    protected function addKeyToAgent()
    {
        $privateKeyContent = file_get_contents($this->getPrivateKey());
        $cmd = shellescapef('echo %s | ssh-add /dev/stdin', $privateKeyContent);
        $this->exec($cmd, $o, $r);
        if ($r !== 0) {
            error("SSH: Agent forwarding failed, can't add key");
        }
    }

    function exec($cmd, &$o, &$r)
    {

        $inBackground = substr($cmd, -2) === ' &';
        if (!$inBackground) {
            $cmd .= ';echo "exitCode=$?"';
        }

        // workaround for ssh2_exec(), because $env param is not working
        $cmd = $this->getEnvVarsExpr() . $cmd;

        $stream = ssh2_exec($this->connection, $cmd);
        if (!is_resource($stream)) {
            error("SSH: ssh2_exec returned " . var_export($stream, true) . ', resource expected');
        }
        $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        stream_set_blocking($errorStream, !$inBackground);
        stream_set_blocking($stream, !$inBackground);

        $o = stream_get_contents($stream);

        if (!$inBackground) {
            // fetch exit code
            $exitCodeRx = '~exitCode=([0-9]+)$~';
            preg_match($exitCodeRx, $o, $ms);
            $r = (int)$ms[1];
            $o = preg_replace($exitCodeRx, '', $o);
        } else {
            $r = 1;
        }

        $o .= stream_get_contents($errorStream);

        fclose($errorStream);
        fclose($stream);

        // make output the same as exec, we have extra \n in the end if ssh2_exec (probably because ";echo")
        $o = preg_replace('~\n$~', '', $o);

        $o = explode("\n", $o);

    }

    protected function getEnvVarsExpr()
    {
        $expr = '';
        foreach ($this->env as $k => $v) {
            $expr .= shellescapef('export %s=%s', $k, $v) . ' && ';
        }
        return $expr;
    }

    public function uploadFile($localFile, $remoteFile)
    {
        // done by scp because big difference in speed
        $this->validateScp();

        exec(cmd(
            'scp -P %s -i %s %s %s:%s',
            $this->port,
            $this->getPrivateKey(),
            $localFile,
            // force to use IP because of it greatly accelerate connection especially in case of byte hosting
            $this->login . '@' . $this->hostIp,
            $remoteFile
        ), $o, $r);

        return $r === 0;
    }

    public function downloadFile($remoteFile, $localFile)
    {
        // done by scp because big difference in speed
        $this->validateScp();

        exec(cmd(
            'scp -P %s -i %s %s:%s %s',
            $this->port,
            $this->getPrivateKey(),
            // force to use IP because of it greatly accelerate connection especially in case of byte hosting
            $this->login . '@' . $this->hostIp,
            $remoteFile,
            $localFile
        ), $o, $r);

        return $r === 0;
    }

    protected function validateScp()
    {
        if (!$this->isHostKnown()) {
            error("Remote server is not registered in known hosts, please connect manually from terminal first");
        }
        if (!$this->isPrivateKeySecure()) {
            $privateKey = preg_replace('~^.+/\.ssh/~', '.ssh/', $this->getPrivateKey());
            error("Permissions for '$privateKey' are too open. It is required that your private key files are NOT accessible by others.");
        }
    }

    protected function getPrivateKey()
    {
        $keysFolder = USER_HOME . '.ssh/';

        if (!empty($this->identityFile)) {
            $key = $keysFolder . $this->identityFile;
        } else {
            $key = $keysFolder . 'id_dsa.pem';
            if (!is_readable($key)) {
                $key = $keysFolder . 'id_rsa';
            }
        }

        if (!is_readable($key)) {
            error("SSH private key \"~/.ssh/" . basename($key) . "\" not found");
        }

        return $key;
    }

    protected function getPublicKey()
    {
        $keysFolder = USER_HOME . '.ssh/';

        if (!empty($this->identityFile)) {
            $key = $keysFolder . $this->identityFile . '.pub';
            if (!is_readable($key)) {
                error("SSH public key \"~/.ssh/{$this->identityFile}\" not found");
            }
            return $key;
        }

        $search = array('id_dsa.pub', 'id_rsa.pub', 'authorized_keys', 'authorized_keys2');
        foreach ($search as $key) {
            $key = $keysFolder . $key;
            if (is_readable($key)) {
                return $key;
            }
        }

        error("No SSH public key found");
    }

    protected function isPrivateKeySecure()
    {
        $keyPerms = substr(decoct(fileperms($this->getPrivateKey())), -3);
        return $keyPerms === '600';
    }

    protected function isHostKnown()
    {
        $hostIp = $this->hostIp;
        $port = $this->port;
        $hostExpr = $port == 22 ? escapeshellarg($hostIp) : shellescapef("[%s]:%s", $hostIp, $port);
        exec('ssh-keygen -F ' . $hostExpr, $o, $r);
        $isKnown = $r === 0 && trim(implode("\n", $o)) !== '';
        if (!$isKnown && !$this->doHostNeedStrictKeyCheck()) {
            $isKnown = $this->addHostToKnownList();
        }
        return $isKnown;
    }

    protected function doHostNeedStrictKeyCheck()
    {
        $ipExceptionRegexp = Config::getNode('ssh/strictHostKeyChecking/ipExceptionRegexp');
        if (!is_null($ipExceptionRegexp)) {
            $expression = str_replace('~', '\\~', $ipExceptionRegexp);
            return !preg_match("~{$expression}~", $this->hostIp);
        }
        return true;
    }

    protected function addHostToKnownList()
    {
        $port = $this->port;
        $portExpr = $port == 22 ? '' : '-p' . escapeshellarg($port);
        // use exec without cmd function to get stdout only
        exec("ssh-keyscan $portExpr -H " . escapeshellarg($this->hostIp), $o, $r);
        if ($r !== 0) {
            return false;
        }
        foreach ($o as $key) {
            file_put_contents(USER_HOME . '.ssh/known_hosts', $key . "\n", FILE_APPEND);
        }
        return $r === 0;
    }

}
