<?php

function auth()
{
    // allow run from console
    if (php_sapi_name() === 'cli') {
        return;
    }
    if ((string)$GLOBALS['PWD'] !== $_POST['PWD']) {
        error('unauthorized');
    }
    if (time() - filemtime(__FILE__) > 3600 * 8) {
        error('expired');
    }
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

function getDbCredentials($type = 'external')
{
    if (!in_array($type, array('app', 'external'))) {
        error('Invalid type passed to the ' . __FUNCTION__);
    }

    if ($type === 'external') {
        return getExternalDbCredentials();
    } else {
        return getAppDbCredentials();
    }
}

function getAppDbCredentials()
{
    $defaultCred = array(
        'host' => 'localhost',
        'username' => '',
        'password' => '',
        'port' => 0, // 0 mean default from my.cnf
        'dbname' => '',
    );

    global $instInfo;
    if ($instInfo->project->type === 'magento2') {
        $cred = getAppDbCredentialsM2();
    } else {
        $cred = getAppDbCredentialsM1();
    }

    if (!$cred) {
        $cred = $defaultCred;
    }
    return (object)array_merge($defaultCred, (array)$cred);
}

function getAppDbCredentialsM2()
{
    $m2Config = getM2EnvConfig();
    if (!isset($m2Config['db']['connection']['default'])) {
        return false;
    }
    $cred = $m2Config['db']['connection']['default'];
    if (strpos($cred['host'], ':')) {
        $hostPairs = explode(':', $cred['host']);
        $cred['host'] = $hostPairs[0];
        $cred['port'] = $hostPairs[1];
    }
    return $cred;
}

function getAppDbCredentialsM1()
{
    foreach (array('local.xml', 'local.local.xml', 'local.database.xml') as $file) {

        $fileName = MAGE_ROOT . "app/etc/$file";

        if (!file_exists($fileName) || !($config = simplexml_load_file($fileName)) || !is_object($config)) {
            continue;
        }

        if (!count($result = $config->xpath('global/resources/default_setup/connection'))) {
            continue;
        }

        $dbNode = $result[0];
        $cred['host'] = (string)$dbNode->host;
        if (strpos($cred['host'], ':')) {
            $hostPairs = explode(':', $cred['host']);
            $cred['host'] = $hostPairs[0];
            $cred['port'] = $hostPairs[1];
        }

        return array_merge(
            $cred,
            array(
                'username' => (string)$dbNode->username,
                'password' => (string)$dbNode->password,
                'dbname' => (string)$dbNode->dbname,
            )
        );
    }
}

function getExternalDbCredentials()
{
    global $instInfo;

    $appCred = getAppDbCredentials();

    if ($instInfo->webServer->type !== 'docker') {
        return $appCred;
    }

    $extCred = getDockerExternalMysqlServerCredentials();
    // merge to add db name
    return (object)array_merge((array)$appCred, (array)$extCred);
}

function getDockerExternalMysqlServerCredentials()
{
    if (!isAppDockerized()) {
        return false;
    }

    if (isDevtoolInDocker()) {
        return (object)array(
            'host' => 'database',
            'username' => 'root',
            'password' => 'abcABC123',
            'port' => 3306
        );
    }

    $cred = array(
        'host' => 'database',
        'username' => 'root',
        'password' => 'abcABC123',
        'port' => 3306,
    );

    /*
     * find the IP address of the container
     * Docker network with name 'devtool' should exist. To create it, run `docker network create devtool`
     */
    $containerName = getDockerContainerName('database');
    $inspect = json_decode(exece('docker inspect %s', $containerName));
    $inspect = $inspect[0];
    $host = $inspect->NetworkSettings->Networks->devtool->IPAddress;

    $port = false;
    // get the first exposed port
    if (isset($inspect->Config->ExposedPorts)) {
        foreach ($inspect->Config->ExposedPorts as $k => $v) {
            $port = $k;
            break;
        }
    }
    if ($port && preg_match('/(\d+).*/', $port, $matches)) {
        $port = $matches[1];
    }

    if (!$host || !$port) {
        return false;
    }
    $cred['host'] = $host;
    $cred['port'] = $port;

    // we assume that MYSQL_ROOT_PASSWORD is set in the container configuration, not auto generated
    $env = $inspect->Config->Env;
    $env = implode('&', $env);
    parse_str($env, $env);
    $cred['username'] = 'root';
    $cred['password'] = $env['MYSQL_ROOT_PASSWORD'];
    return (object)$cred;
}

function getDockerContainerName($name)
{
    return getDockerComposeProjectName() . '_' . $name . '_1';
}

function isDevtoolInDocker()
{
    return file_exists('/.dockerenv');
}

function isAppDockerized()
{
    return file_exists(MAGE_ROOT . 'docker-compose.yml') && file_exists(MAGE_ROOT . 'app/etc/docker-compose.local.yml');
}

function getDockerComposeProjectName()
{
    if (!file_exists(MAGE_ROOT . '.env')) {
        return false;
    }

    $env = file_get_contents(MAGE_ROOT . '.env');
    if (!preg_match('~[^;#]\s*COMPOSE_PROJECT_NAME\s*=\s*([^\s]+)~ism', $env, $matches)) {
        return false;
    }

    return $matches[1];
}

function getM2EnvConfig()
{
    $m2EnvFile = MAGE_ROOT . 'app/etc/env.php';
    if (file_exists($m2EnvFile)) {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($m2EnvFile, true);
        }
        return include $m2EnvFile;
    }
    return false;
}

function db($credentials = null)
{
    static $pdo = null;
    if (is_null($pdo) && is_null($credentials)) {
        return false;
    }
    if (!is_null($credentials)) {
        try {
            $pdo = new \PDO(
                'mysql:host=' . $credentials->host . ';port=' . $credentials->port, $credentials->username,
                $credentials->password
            );
        } catch (\Exception $e) {
            return false;
        }
        if (!$pdo->query("use `" . $credentials->dbname . '`')) {
            return false;
        }
    }
    return $pdo;
}

function error($message, $exitCode = 1)
{
    if (php_sapi_name() !== 'cli') {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    }
    echo $message;
    exit($exitCode);
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

function cpuCoresCount()
{
    return (int)`cat /proc/cpuinfo | grep -c processor`;
}

function getForeignKeys()
{

    $foreignKeys = array();

    $sql = "
        select *
        from information_schema.key_column_usage
        where referenced_table_name is not null
        and table_schema = database()
    ";
    foreach (db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $foreignKey) {
        $foreignKeys[$foreignKey['CONSTRAINT_NAME']] = array(
            'table' => $foreignKey['TABLE_NAME'],
            'column' => $foreignKey['COLUMN_NAME'],
            'referenced_table' => $foreignKey['REFERENCED_TABLE_NAME'],
            'referenced_column' => $foreignKey['REFERENCED_COLUMN_NAME'],
            'constraint_name' => $foreignKey['CONSTRAINT_NAME'],
        );
    }

    $sql = "
        select *
        from information_schema.referential_constraints
        where constraint_schema = database();
    ";
    foreach (db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $foreignKey) {
        $fk = &$foreignKeys[$foreignKey['CONSTRAINT_NAME']];
        $fk['onupdate'] = strtolower($foreignKey['UPDATE_RULE']);
        $fk['ondelete'] = strtolower($foreignKey['DELETE_RULE']);
    }

    $foreignKeysGrouped = array();
    foreach ($foreignKeys as $foreignKey) {
        $foreignKey['to_update'] = getForeignKeyRowsToUpdate($foreignKey);
        $foreignKey['to_delete'] = getForeignKeyRowsToDelete($foreignKey);
        $foreignKeysGrouped[$foreignKey['referenced_table']][$foreignKey['referenced_column']][] = $foreignKey;
    }

    return $foreignKeysGrouped;

}

function getForeignKeyRowsToUpdate($foreignKey)
{

    extract($foreignKey);

    $where = getForeignKeyUpdateCondition($foreignKey);

    $sql = "
        select count(*) as 'count'
        from $table t
        left join $referenced_table rt on (t.$column = rt.$referenced_column)
        where $where
    ";

    $result = db()->query($sql)->fetch();

    return (int)$result['count'];

}

function getForeignKeyUpdateCondition($foreignKey)
{

    extract($foreignKey);

    switch ($onupdate) {
        case 'no action':
        case 'restrict':
        case 'cascade':
            $where1 = '0';
            break;
        case 'set null':
            $where1 = "t.$column is not null and rt.$referenced_column is null";
            break;
    }

    switch ($ondelete) {
        case 'no action':
        case 'restrict':
        case 'cascade':
            $where2 = '0';
            break;
        case 'set null':
            $where2 = "t.$column is not null and rt.$referenced_column is null";
            break;
    }

    return "($where1) or ($where2)";

}

function getForeignKeyRowsToDelete($foreignKey)
{

    extract($foreignKey);

    $where = getForeignKeyDeleteCondition($foreignKey);

    $sql = "
            select count(*) as 'count'
            from $table t
            left join $referenced_table rt on (t.$column = rt.$referenced_column)
            where $where
        ";

    $result = db()->query($sql)->fetch();

    return (int)$result['count'];

}

function getForeignKeyDeleteCondition($foreignKey)
{

    extract($foreignKey);

    switch ($onupdate) {
        case 'no action':
        case 'set null':
        case 'cascade':
            $where1 = '0';
            break;
        case 'restrict':
            $where1 = "t.$column is not null and rt.$referenced_column is null";
            break;
    }

    switch ($ondelete) {
        case 'no action':
        case 'set null':
            $where2 = '0';
            break;
        case 'restrict':
        case 'cascade':
            $where2 = "t.$column is not null and rt.$referenced_column is null";
            break;
    }

    return "($where1) or ($where2)";

}

function fixForeignKeys()
{

    $updated = 0;
    $deleted = 0;

    db()->query('set foreign_key_checks = 0');

    $foreignKeys = getForeignKeys();

    foreach ($foreignKeys as $columns) {
        foreach ($columns as $keys) {
            foreach ($keys as $key) {

                if ($key['to_update']) {

                    $where = getForeignKeyUpdateCondition($key);

                    $sql = "
                        update {$key['table']} t
                        left join {$key['referenced_table']} rt on (t.{$key['column']} = rt.{$key['referenced_column']})
                        set t.{$key['column']} = null
                        where $where
                    ";
                    $updated += db()->query($sql)->rowCount();

                }

                if ($key['to_delete']) {

                    $where = getForeignKeyDeleteCondition($key);

                    $sql = "
                        delete t
                        from {$key['table']} t
                        left join {$key['referenced_table']} rt on (t.{$key['column']} = rt.{$key['referenced_column']})
                        where $where
                    ";
                    $deleted += db()->query($sql)->rowCount();

                }

            }
        }
    }

    db()->query('set foreign_key_checks = 1');

    return compact('updated', 'deleted');

}

function isSolidSessionStartBug()
{
    if (!file_exists($tmpFile = 'app/code/local/Solid/Customer/etc/config.xml')) {
        return;
    }
    $xml = simplexml_load_file($tmpFile);
    if (!is_object($xml)) {
        return;
    }
    $xres = $xml->xpath('global/events/controller_front_init_before/observers/solid_customer/method');
    if (!count($xres)) {
        return;
    }
    return (string)$xres[0] === 'setCurrencyFromCustomerGroup';
}

function getCustomerShareScope()
{
    $scope = 'global';
    $sql = "select * from core_config_data where path = 'customer/account_share/scope'";
    $row = db()->query($sql)->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $scope;
    }
    return $row['value'] === '1' ? 'website' : $scope;
}

function exece()
{
    $args = func_get_args();
    if (!count($args)) {
        return false;
    }
    $cmd = array_shift($args);
    if (is_array($cmd)) {
        $cmd = implode(' 2>&1 && ', $cmd);
    }
    $cmd .= ' 2>&1';
    if (count($args)) {
        foreach ($args as &$arg) {
            $arg = escapeshellarg($arg);
        }
        array_unshift($args, $cmd);
        $cmd = call_user_func_array('sprintf', $args);
    }

    $cmd = 'cd ' . escapeshellarg(MAGE_ROOT) . ' 2>&1 && ' . $cmd;
    \exec($cmd, $o, $r);
    $o = implode("\n", $o);
    if ($r !== 0) {
        throw new Exception($o);
    }
    return $o;
}

function initMagento2()
{
    if (isset($_SERVER['MAGE_MODE']) && !in_array($_SERVER['MAGE_MODE'], array('production', 'developer'))) {
        // fix for: Uncaught InvalidArgumentException: Unknown application mode
        unset($_SERVER['MAGE_MODE']);
    }

    require_once MAGE_ROOT . 'app/bootstrap.php';
    $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
    $objectManager = $bootstrap->getObjectManager();
    $objectManagerConfigLoader = $objectManager->get('Magento\Framework\ObjectManager\ConfigLoaderInterface');
    $objectManager->configure($objectManagerConfigLoader->load(Magento\Framework\App\Area::AREA_ADMINHTML));
    $objectManager->get('Magento\Framework\App\State')->setAreaCode(Magento\Framework\App\Area::AREA_ADMINHTML);
    return $bootstrap;
}
