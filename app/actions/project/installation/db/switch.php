<?php

$result = array(
    'switch' => true,
    'flushCaches' => true,
);

try {

    $currentBaseUrls = \Mysql::query('select * from core_config_data where path like "web/%secure%url" and value like "http%"')->fetchAll();

    /** @var \Project\Installation $inst */
    if ($inst->webServer->type !== 'docker') {
        /** @var stdClass $ARG */
        $dbUsedBy = $inst->findInstallationsByDbName($ARG->database, false);
        if (count($dbUsedBy) > 0) {
            $instList = '';
            foreach ($dbUsedBy as $installation) {
                $instList .= "  " . $installation['project'] . " -> " . $installation['name'] . "\n";
            }
            error("Database already used by: \n" . $instList);
        }
    }

    if (
        !$inst->setDbCredentials(array('dbname' => $ARG->database)) ||
        !Mysql::db($ARG->database) // switch current db to new one
    ) {
        error('Database switch was not completed correctly');
    }

    // apply $currentBaseUrls to new database
    foreach ($currentBaseUrls as $url) {
        \Mysql::query(
            'update core_config_data set value = ? where path = ? and scope = ? and scope_id = ?;',
            array($url['value'], $url['path'], $url['scope'], $url['scope_id'])
        );
    }

    $inst->fixBaseUrls();
    $inst->fixBaseUrlsSsl();

    $inst->magento->adjustDbToDev();

} catch (Exception $e) {
    $result['switch'] = $e->getMessage();
}

if ($ARG->doFlushCaches) {
    $result['flushCaches'] = $inst->magento->flushCaches();
}

return $inst->form('db/switch/result', $result);
