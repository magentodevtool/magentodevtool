<?php

$remoteInst = \Projects::getInstallation($inst->source, $inst->project->name, $dbImport->remoteInstallationName);
$remoteDump = $dbImport->dump;

$destFile = $inst->_appRoot . 'var/' . $remoteDump;
$destFolder = dirname($destFile);
if (!is_dir($destFolder)) {
    umask(0);
    mkdir($destFolder, 0777, true);
}

$r = $remoteInst->downloadFile('var/' . $remoteDump, $destFile);

return array('success' => $r);
