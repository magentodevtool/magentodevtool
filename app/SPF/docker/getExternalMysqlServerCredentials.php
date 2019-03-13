<?php

# incspf exec
# incspf error
# incspf docker/isDevtoolInDocker
# incspf docker/isAppDockerized
# incspf docker/getContainerName

namespace SPF\docker;

/**
 * @return bool
 */
function getExternalMysqlServerCredentials()
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
    $containerName = getContainerName('database');
    $inspect = \json_decode(\SPF\exec('docker inspect %s', $containerName));
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
