<?php

# incspf mage/getM2EnvConfig

namespace SPF\db;

function getAppCredentials()
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
        $cred = getAppCredentialsM2();
    } else {
        $cred = getAppCredentialsM1();
    }

    if (!$cred) {
        $cred = $defaultCred;
    }
    return (object)array_merge($defaultCred, (array)$cred);

}

function getAppCredentialsM2()
{
    $m2Config = \SPF\mage\getM2EnvConfig();
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

function getAppCredentialsM1()
{
    foreach (array('local.xml', 'local.local.xml', 'local.database.xml') as $file) {

        $fileName = "app/etc/$file";

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
