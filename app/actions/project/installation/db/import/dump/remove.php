<?php

$targetInst = $inst;
if (isset($dbImport->remoteInstallationName)) {
    $targetInst = \Projects::getInstallation($inst->source, $inst->project->name, $dbImport->remoteInstallationName);
}

$backupFileName = $dbImport->dump;

if (empty($backupFileName) || strpos($backupFileName, '/') === 0) {
    error('Invalid file name');
}

return array('success' => $targetInst->exec('rm %s', 'var/' . $backupFileName) === '');
