<?php

if (isset($dbImport->remoteInstallationName)) {
    $inst = Projects::getInstallation($inst->source, $inst->project->name, $dbImport->remoteInstallationName);
}

$backups = $inst->getDbBackups();

$result = array();
foreach ($backups as $backup) {
    $result[$backup['file']] = $backup['file'] . ' - ' . $backup['size'];
}

return array('success' => true, 'list' => $result);