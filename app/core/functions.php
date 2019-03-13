<?php

function auth()
{

    $noAuthActions = [
        'timeEstimation/api/save' => 1,
        'timeEstimation/api/load' => 1,
    ];
    $noAuthViews = [
        'timeEstimation/details' => 1,
        'timeEstimation/details2' => 1,
    ];

    if (isset($_GET['action']) && isset($noAuthActions[$_GET['action']])) {
        return;
    }
    if (isset($_GET['view']) && isset($noAuthViews[$_GET['view']])) {
        return;
    }

    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1') {
        return;
    }

    $config = Config::getData();
    $authConfig = new stdClass();
    if (isset($config->auth)) {
        $authConfig = $config->auth;
    }

    $doIpCheck = true;
    if (isset($authConfig->disableIpCheck)) {
        $doIpCheck = !$authConfig->disableIpCheck;
    }

    if ($doIpCheck) {
        checkIpAccess();
    }

    if (!isset($authConfig->ldap)) {
        return;
    }

    if (!isset($authConfig->ldap->host) || empty($authConfig->ldap->host)) {
        throw new Exception('Invalid host for LDAP in config.json');
    }

    $areCredentialsValid = false;

    if (isset($_SERVER['PHP_AUTH_USER'])) {

        $userLogin = $_SERVER['PHP_AUTH_USER'];
        if (isset($authConfig->ldap->defaultUserDomain) && (strpos($userLogin, '\\') === false)) {
            $userLogin = $authConfig->ldap->defaultUserDomain . '\\' . $userLogin;
        }

        $areCredentialsValid = auth_ldap_cached($authConfig->ldap->host, $userLogin, $_SERVER['PHP_AUTH_PW']);

    }

    if (!$areCredentialsValid) {
        header('WWW-Authenticate: Basic realm="LDAP Authorization"');
        header('HTTP/1.0 401 Unauthorized');
        die('Unauthorized');
    }

}

function checkIpAccess()
{

    $config = Config::getData();
    $allowedIpAddresses = isset($config->allowedIpAddresses) ? $config->allowedIpAddresses : array();
    if (is_object($allowedIpAddresses)) {
        $allowedIpAddresses = (array)$allowedIpAddresses;
    }

    if ($allowedIpAddresses === '*') {
        return;
    }

    if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIpAddresses)) {
        die('No access for ' . $_SERVER['REMOTE_ADDR']);
    }

}

function auth_ldap_cached($host, $user, $password)
{

    if (!extension_loaded('ldap')) {
        error('LDAP extension is missing');
    }

    $success = session_get('lastLdapLoginSuccess');
    $argsHash = sha1("$host, $user, $password");

    if (
        $success
        && ((time() - $success['timestamp']) < 60 * 10)
        /*
         we need to check args hash because session can stay when basic auth was cleaned by open/close browser
         in that case cache will work for any credentials until expire
         reproduced on chrome with "on startup" = "start where you left off"
        */
        && ($success['argsHash'] === $argsHash)
    ) {
        // prolong expire if user is active
        $success['timestamp'] = time();
        session_set('lastLdapLoginSuccess', $success);
        return true;
    }

    if (auth_ldap($host, $user, $password)) {
        session_set('lastLdapLoginSuccess', array(
            'timestamp' => time(),
            'argsHash' => $argsHash,
        ));
        return true;
    }

    return false;

}

function auth_ldap($host, $user, $password)
{

    $username = preg_replace('~^[^\\\\]+\\\\~', '', $user); // remove domain if present
    if ($username === '' || $password === '') {
        // ldap bind return true for empty user or password!?
        return false;
    }
    $ldapConnection = @ldap_connect($host);
    if (!$ldapConnection) {
        throw new Exception('Failed to connect to LDAP server');
    }

    return @ldap_bind($ldapConnection, $user, $password);

}

// this function is intended to hold session as less as possible in order to avoid session lock for background requests e.g db import progress
function session_get($name)
{
    session_start();
    $value = isset($_SESSION[$name]) ? $_SESSION[$name] : null;
    session_write_close();
    return $value;
}

// this function is intended to hold session as less as possible in order to avoid session lock for background requests e.g db import progress
function session_set($name, $value)
{
    session_start();
    $_SESSION[$name] = $value;
    session_write_close();
}

function validateRequiredFields($fields, $data)
{
    foreach ($fields as $field) {
        if (!isset($data->$field) || empty($data->$field)) {
            return $field;
        }
    }
    return true;
}

function is_writable_by_other($file)
{

    $file = rtrim($file, '/');
    $parent = preg_replace('~/[^/]+$~', '/', $file);
    $pathArray = explode('/', trim($file, '/'));
    $fileName = end($pathArray);

    $cmd = cmd('ls -l %s', $parent);
    $info = trim(`$cmd`);
    foreach (explode("\n", $info) as $infoLine) {
        if (preg_match('~ .+ .+ .+ ' . preg_quote($fileName) . '$~', $infoLine)) {
            if ($infoLine[8] == 'w') {
                return true;
            }
        }
    }

    return false;

}

function is_file_writable_by_user($file, $user)
{
    $cmd = cmd('sudo -u %s test -w %s', $user, $file);
    exec($cmd, $o, $r);
    return $r === 0;
}

function cmd()
{
    $args = func_get_args();
    if (!count($args)) {
        return false;
    }
    $cmd = &$args[0];
    if (is_array($cmd)) {
        $cmd = implode(' ' . cmd_stderror_redirection() . ' && ', $cmd);
    }
    $cmd = call_user_func_array('shellescapef', $args);
    if (substr($cmd, -2) === ' &') {
        return $cmd;
    }
    return $cmd . ' ' . cmd_stderror_redirection();
}

function cmd_stderror_redirection($redirection = null)
{
    static $current_redirection = '2>&1';
    if (!is_null($redirection)) {
        $current_redirection = $redirection;
    }
    return $current_redirection;
}

function cmd_stderror_redirection_reset()
{
    cmd_stderror_redirection('2>&1');
}

function shellescapef()
{
    $args = func_get_args();
    if (!count($args)) {
        return false;
    }
    $template = array_shift($args);
    if (!count($args)) {
        return $template;
    }
    foreach ($args as &$arg) {
        $arg = escapeshellarg($arg);
    }
    array_unshift($args, $template);
    return call_user_func_array('sprintf', $args);
}

function resolveIp($host)
{
    $ip = gethostbyname($host);
    if (!isIp($ip)) {
        error("Host \"$host\" resolving failed");
    }
    return $ip;
}

function isIp($str)
{
    return ip2long($str) !== false;
}

function execCallback($command, $chunkCb, $usleep = 500)
{

    static $chunkSize = 8192; // standard

    $process = proc_open($command,
        array(
            array("pipe", "r"),
            array("pipe", "w"),
            array("pipe", "w")
        ),
        $pipes
    );
    stream_set_blocking($pipes[1], 0);

    $chunkLeft = '';
    $isNewLine = true;
    while (!feof($pipes[1])) {

        $chunk = fread($pipes[1], $chunkSize);
        if (!is_string($chunk)) {
            if ($usleep) {
                usleep($usleep);
            }
            continue;
        }
        $chunk = $chunkLeft . $chunk;
        $chunkLeft = '';

        while (($pos = strpos($chunk, "\n")) !== false) {
            $chunkChunk = substr($chunk, 0, $pos + 1);
            call_user_func($chunkCb, $chunkChunk, $isNewLine);
            $chunk = (string)substr($chunk, $pos + 1);
            $isNewLine = true;
        }

        if ($chunk !== '') {
            if (strlen($chunk) >= $chunkSize) {
                call_user_func($chunkCb, $chunk, $isNewLine);
                $isNewLine = false;
            } else {
                $chunkLeft = $chunk;
            }
        }

    }

    if ($chunkLeft !== '') {
        call_user_func($chunkCb, $chunk, $isNewLine);
    }

    if ($err = stream_get_contents($pipes[2])) {
        proc_close($process);
        error("execCallback error for \"$command\":\n$err");
    }

    proc_close($process);

}

function simpleFilterToRxArray($filter)
{
    $rx = array();
    $filters = array_map('trim', explode(',', $filter));
    foreach ($filters as $filter) {
        if ($filter === '') {
            continue;
        }
        $filterType = $filter{0} === '!' ? '-' : '+';
        if ($filterType === '-') {
            $filter = substr($filter, 1);
        }
        $filterWords = array_map('preg_quote', explode('*', $filter));
        $rx[] = array(
            'type' => $filterType,
            'rx' => '~^' . implode('.*', $filterWords) . '$~i',
        );

    }
    return $rx;
}

function simpleFilterTest($filter, $string)
{
    $filters = simpleFilterToRxArray($filter);
    foreach ($filters as $i => $filter) {
        if ($filter['type'] === '-') {
            if (preg_match($filter['rx'], $string)) {
                return false;
            }
            unset($filters[$i]);
        }
    }
    foreach ($filters as $filter) {
        if (preg_match($filter['rx'], $string)) {
            return true;
        }
    }
    return false;
}

function saveJson($file, $object)
{
    file_put_contents(
        $file,
        json_encode(
            (object)$object,
            (PHP_MAJOR_VERSION * 10 + PHP_MINOR_VERSION) >= 54 ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : 0
        )
    );
}

function html2text($html)
{
    return htmlspecialchars($html);
}

function var2htmlValue($var)
{
    return html2text(json_encode($var));
}

function getDateAgo($date)
{

    $secondsAgo = time() - strtotime($date);

    $units = array(
        31536000 => 'years',
        2592000 => 'months',
        604800 => 'weeks',
        86400 => 'days',
        3600 => 'hours',
        60 => 'minutes',
        1 => 'seconds'
    );

    foreach ($units as $unit => $unitName) {
        if ($secondsAgo < $unit) {
            continue;
        }
        return round($secondsAgo / $unit, 1) . " " . $unitName;
    }

    return "0 seconds";

}

function incrementVersion($currentVersion)
{
    preg_match_all('~([0-9]+)~', $currentVersion, $ms);
    if (!isset($ms[1]) || !count($ms[1])) {
        return '';
    }
    $lastNumber = end($ms[1]);
    $nextNumber = $lastNumber + 1;
    $nextVersion = preg_replace('~([0-9]+)([^0-9]*)$~', $nextNumber . '$2', $currentVersion);
    return $nextVersion;
}

function getApacheVersion()
{
    preg_match('~Apache/([0-9]+\.[0-9]+\.[0-9]+)~ism', `/usr/sbin/apache2 -v`, $ms);
    return $ms[1];
}

function sudo_file_put_contents($fileName, $content)
{
    $tmpFile = tempnam('/tmp', 'devtool_');
    chmod($tmpFile, 0644);
    file_put_contents($tmpFile, $content);
    exec(cmd('sudo cp %s %s', $tmpFile, $fileName));
    unlink($tmpFile);
}

function sudo_mkdir($path)
{
    exec(cmd('sudo mkdir %s', $path));
}

function reloadApache()
{
    `sudo /etc/init.d/apache2 reload`;
}

function reloadNginx()
{
    exec(cmd('sudo service nginx reload'));
}

function reloadFpm()
{
    exec(cmd('sudo service ' . getFpmServiceName() . ' reload'));
}

function getFpmServiceName()
{
    static $name;
    if ($name !== null) {
        return $name;
    }
    exec(cmd('sudo systemctl -r --type service --all'), $o, $r);
    $o = implode("\n", $o);
    if ($r !== 0) {
        error($o);
    }
    if (!preg_match_all('~php.*-fpm~', $o, $m)) {
        error('PHP FPM service not found');
    }
    $names = $m[0];
    rsort($names);
    return $name = $names[0];
}

function isNginxInstalled()
{
    return trim(`dpkg -l | grep nginx`) != '';
}

function isPhpFpmInstalled()
{
    try {
        return (bool)getFpmServiceName();
    } catch (Exception $e) {
        return false;
    }
}

function isGitInstalled()
{
    return trim(`which git`) != '';
}

function stripSharpComments($content)
{
    $contentLines = explode("\n", $content);
    foreach ($contentLines as $key => $line) {
        if (preg_match('~^\s*#~', $line)) {
            unset($contentLines[$key]);
        }
    }
    return implode("\n", $contentLines);
}

function getServiceListening($service, $skipPorts = true, $portFilter = '80')
{
    if (empty($service)) {
        return array();
    }
    $list = array();
    exec(cmd('sudo netstat -nlp | grep -i %s | awk \'{ print $4 }\'', $service), $output);
    foreach ($output as $ipPort) {
        $ipPort = preg_replace('~^::1~', '127.0.0.1', $ipPort);
        $ipPort = preg_replace('~^::~', '0.0.0.0', $ipPort);
        if (!is_null($portFilter) && !preg_match('~:' . preg_quote($portFilter, '~') . '$~', $ipPort)) {
            continue;
        }
        $list[] = $skipPorts ? preg_replace('~:[^:]+$~', '', $ipPort) : $ipPort;
    }

    return $list;
}

/**
 * Return service local listening ip.
 *
 * @param string $serviceName
 *
 * @return string
 */
function getServiceLocalIp($serviceName)
{
    $listOfIps = getServiceListening($serviceName);

    if (!count($listOfIps)) {
        // default addresses
        return $serviceName == 'apache' ? '127.0.0.1' : ' 127.0.0.2';
    }

    // return 127.* if exists (helps in case of several ips e.g. when both projects web-server and SOLR are installed locally)
    foreach ($listOfIps as $ip) {
        if (strpos($ip, '127.') === 0) {
            return $ip;
        }
    }

    return $listOfIps[0];
}

function getApacheNameVirtualHost()
{
    $localIp = getServiceLocalIp('apache');
    $localIp = $localIp === '0.0.0.0' ? '*' : $localIp;
    return "$localIp:80";
}

function spf()
{
    $args = func_get_args();
    spf::$function = array_shift($args);
    spf::$args = $args;
    return spf::run();
}

function object_walk_recursive($object, $callback)
{
    foreach ($object as &$value) {
        if (is_array($value) || is_object($value)) {
            object_walk_recursive($value, $callback);
        } else {
            $callback($value);
        }
    }
}

function object_clone_recursive($object)
{
    $object = clone $object;
    foreach ($object as $key => $value) {
        if (is_object($value)) {
            $object->{$key} = object_clone_recursive($value);
        }
    }
    return $object;
}

function object_rebuild_recursive($object, $className)
{
    $newObject = new $className;
    foreach ($object as $k => $v) {
        $v = is_object($v) ? object_rebuild_recursive($v, $className) : $v;
        $newObject->$k = $v;
    }
    return $newObject;
}

function version_extract($name)
{
    preg_match('~\d+(\.\d+)+~', $name, $ms);
    if (!isset($ms[0])) {
        preg_match('~\d+~', $name, $ms);
    }
    return isset($ms[0]) ? $ms[0] : 0;
}

function listdir($dir, $prependDir = false, $recursive = false, $currPath = '')
{
    if (!is_dir($dir)) {
        return array();
    }
    $currPath = $prependDir ? $dir : $currPath;
    $currPath = $currPath !== '' ? rtrim($currPath, '/') . '/' : '';
    $files = array();
    foreach (scandir($dir) as $file) {
        if (in_array($file, array('.', '..'))) {
            continue;
        }
        if ($recursive && is_dir("$dir/$file")) {
            $files = array_merge($files, listdir("$dir/$file", false, true, $currPath . $file . '/'));
            continue;
        }
        $files[] = $currPath . $file;
    }
    return $files;
}

function getNginxOwner()
{
    // todo: parse values from config /etc/nginx
    return array(
        'user' => 'www-data',
        'group' => 'www-data'
    );
}

function fpm_quote($value)
{
    return '"' . str_replace('\\', '\\\\', $value) . '"';
}

function humanSizeFormat($bytes)
{
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . 'G';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . 'M';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . 'K';
    } elseif ($bytes > 1) {
        $bytes = $bytes;
    } elseif ($bytes == 1) {
        $bytes = $bytes;
    } else {
        $bytes = '0';
    }

    return $bytes;
}

function getDevtoolCommitsBehindCount()
{
    exec(
        cmd(array('cd %s', 'git fetch -q 2>&1 && git log --pretty=oneline HEAD..origin/master 2>&1'), APP_DIR . '..'),
        $output, $error
    );
    if ($error !== 0) {
        error(implode("\n", $output));
    }
    foreach ($output as $key => $val) {
        if (preg_match('~^[0-f]+ Merge ~', $val)) {
            unset($output[$key]);
        }
    }
    return count($output);
}

class Hosts
{

    static protected $file = '/etc/hosts';

    static function getDomainIp($domain)
    {
        $ip = null;
        foreach (explode("\n", file_get_contents(static::$file)) as $line) {
            $match = preg_match(
                static::getDomainIpLineRegexp($domain),
                $line,
                $ms
            );
            if ($match) {
                $ip = $ms[1];
            }
        }
        return $ip;
    }

    static function setDomainIp($domain, $ip)
    {
        if (static::getDomainIp($domain) === null) {
            // add new line
            $content = file_get_contents(static::$file);
            if (substr($content, -1) !== "\n") {
                $content .= "\n";
            }
            $content .= "$ip $domain\n";
        } else {
            // update line
            $content = array();
            foreach (explode("\n", file_get_contents(static::$file)) as $line) {
                $content[] = preg_replace(
                    static::getDomainIpLineRegexp($domain),
                    "$ip\$2",
                    $line
                );
            }
            $content = implode("\n", $content);
        }
        sudo_file_put_contents(static::$file, $content);
    }

    static protected function getDomainIpLineRegexp($domain)
    {
        return '~^\s*([^\s]+)(.*\s+' . preg_quote($domain, '~') . '(\s+|\s+.*|$))$~';
    }

}

function xml_load_string($str)
{
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    if (!$xml = @simplexml_load_string($str)) {
        $errorsText = '';
        $nl = '';
        foreach (libxml_get_errors() as $error) {
            $errorsText .= $nl . "\tLine " . $error->line . ', column ' . $error->column . ': ' . trim($error->message);
            $nl = "\n";
        }
        error("XML load failed with following errors:\n$errorsText");
    }
    return $xml;
}

function cpuCoresCount()
{
    return (int)`cat /proc/cpuinfo | grep -c processor`;
}

function isPidRunning($pid)
{
    exec("kill -0 $pid", $o, $r);
    return $r === 0;
}

function file_get_contents_unsecure($file)
{
    $contextOptions = [
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
            "allow_self_signed" => true,
            "verify_depth" => 0,
        ],
    ];
    return file_get_contents($file, null, stream_context_create($contextOptions));
}

function mysqlEscapeString($str)
{
    $replacements = [
        "\x00" => '\x00',
        "\n" => '\n',
        "\r" => '\r',
        "\\" => '\\\\',
        "'" => "\'",
        '"' => '\"',
        "\x1a" => '\x1a'
    ];
    return strtr($str, $replacements);
}
