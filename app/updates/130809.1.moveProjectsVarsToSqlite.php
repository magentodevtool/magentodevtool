<?php

$connection = \SQLite::getDb('devtool');

$connection->exec("
	CREATE TABLE IF NOT EXISTS vars (
	  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	  project varchar(50) NOT NULL,
	  installation varchar(50) NOT NULL,
	  key varchar(250) NOT NULL,
	  value text NOT NULL);");

$projectVarsFilePath = DATA_DIR_INT . 'projectsVars.json';

if (!file_exists($projectVarsFilePath)) {
    return;
}

$projectVars = json_decode(file_get_contents($projectVarsFilePath, true));

foreach ($projectVars as $project => $installations) {
    foreach ($installations as $installation => $vars) {
        foreach ($vars as $key => $value) {
            $inst = Projects\Local::getInstallation($project, $installation);
            $inst->vars->set($key, $value);
        }
    }
}

$tempDir = USER_HOME . '/temp/';
if (!file_exists($tempDir)) {
    mkdir($tempDir);
}
copy($projectVarsFilePath, $tempDir . 'projectsVars.json');
unlink($projectVarsFilePath);
