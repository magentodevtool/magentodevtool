<?php

$dbCred = $inst->getDbCredentials();

if ($inst->type == 'local') {
    if (!Mysql::server($dbCred) || !Mysql::db($dbCred->dbname)) {
        die('Current database credentials must be valid to run this action');
    }
}