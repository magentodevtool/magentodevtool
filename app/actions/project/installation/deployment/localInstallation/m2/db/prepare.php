<?php

/** @var \Project\Installation $inst */
/** @var \Project\Installation $localInst */
$cred = $localInst->getDbCredentials();

if (!\Mysql::server($cred)) {
    deploymentDialog('localInstallation/m2/db/credentials', compact('cred'));
}

if (empty($cred->dbname)) {
    $cred->dbname = $inst->deployment->localInstallation->getDefaultDbName();
    $localInst->setDbCredentials($cred);
}

if (!\Mysql::dbExists($cred->dbname)) {
    \Mysql::createDb($cred->dbname);
}

// create structure backup without data
$dumpFile = 'structure-' . (time() + microtime());
$rai = $inst->uploadRai(false); // - don't schedule rai clean as it will be failed on some workstations due to unknown reason
$inst->dump->create($dumpFile, true, 'all', true);
$inst->removeRai($rai);
$dumpFile .= '.sql.gz';

// download structure backup
$destDumpDir = $localInst->folder . "var/backups/";
if (!is_dir($destDumpDir)) {
    mkdir($destDumpDir, 0777, true);
}
if (!$inst->downloadFile("var/backups/$dumpFile", "$destDumpDir/$dumpFile")) {
    error("Error occurred when downloading structure dump $dumpFile from " . $inst->name);
}

// remove structure backup on remote
$inst->exec('rm %s', "var/backups/$dumpFile");

// extract dump
$dumpArchiveAbs = $localInst->folder . "var/backups/$dumpFile";
$dumpFileAbs = preg_replace('~\.gz$~', '', $dumpArchiveAbs);
$localInst->exec(
    [
        'gunzip -c %s > %s',
        'rm %s',
    ],
    $dumpArchiveAbs, $dumpFileAbs, $dumpArchiveAbs
);

// import structure
$rai = $localInst->uploadRai();
$errorFileAbs = $dumpFileAbs . '.errors';
$initCommand = $localInst->getInitCommandBeforeDumpImport($dumpFileAbs);
$cmd = shellescapef(
    "php %s %s %s %s | MYSQL_PWD=%s mysql -h%s -u%s %s -v $initCommand 2>%s",
    $rai->dir . 'db/backup/read.php', $dumpFileAbs, json_encode('all'), $cred->dbname,
    $cred->password, $cred->host, $cred->username, $cred->dbname, $errorFileAbs
);
$localInst->exec($cmd);

// remove imported dump
unlink($dumpFileAbs);

// check for errors
if (filesize($errorFileAbs)) {
    error("Errors during structure dump import, see $errorFileAbs");
}
unlink($errorFileAbs);
