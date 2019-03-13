<?php

if (empty($dbImport->tables)) {
    $dbImport->tables = 'all';
}

$targetInst = $inst;
if (isset($dbImport->remoteInstallationName)) {
    $targetInst = \Projects::getInstallation($inst->source, $inst->project->name, $dbImport->remoteInstallationName);
}

$result = $targetInst->dump->create($dbImport->dumpFileName, true, $dbImport->tables);

return array('success' => ($result && !$result->wereErrors), 'result' => $result);
