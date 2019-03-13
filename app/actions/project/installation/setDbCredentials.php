<?php
/** @var \Project\Installation $inst */
/** @var \stdClass $ARG */

foreach ($ARG as $k => $v) {
    $ARG->$k = trim($v);
}
$ARG->port = 0;

if (!$inst->setDbCredentials($ARG)) {
    return 'Failed to set credentials';
}

$externalCred = $ARG;
if ($inst->webServer->type === 'docker') {
    // In case of Docker, external host is different from internal
    $externalCred = $inst->getDbCredentials();
}

if (!Mysql::server($externalCred)) {
    return "Connection failed, please check parameters...";
}

if ($inst->webServer->type !== 'docker') {
    $dbUsedBy = $inst->findInstallationsByDbName($ARG->dbname, false);
    if (count($dbUsedBy) > 0) {
        return template(
            'project/installation/installer/fixDbCredentials/dbUsedBy',
            ['installations' => $dbUsedBy]
        );
    }
}

return true;
