<?php

$tables = [
    'eav_entity_type' => 1,
    'eav_attribute_group' => 1,
    'core_config_data' => 1,
    'indexer_state' => 1,
    'setup_module' => 1,
    'store store_group' => 1,
    'store_website' => 1,
    'theme' => 1,
    'translation' => 1,
    'eav_attribute' => 1,
    'customer_eav_attribute' => 1,
    'customer_eav_attribute_website' => 1,
    'catalog_eav_attribute' => 1,
    'eav_attribute_set' => 1,
];

$info = $inst->magento->getInfo();
if ($info->edition === 'Enterprise') {
    $tables['magento_rma_item_eav_attribute'] = 1;
}

/** @var \Project\Installation $inst */
/** @var \Project\Installation $localInst */
$cred = $localInst->getDbCredentials();

// create data backup
$dumpFile = 'deployment-update-' . (time() + microtime());
$inst->dump->create($dumpFile, true, $tables);
$dumpFile .= '.partial.sql.gz';

// download backup
$destDumpDir = $localInst->folder . "var/backups/";
if (!is_dir($destDumpDir)) {
    mkdir($destDumpDir, 0777, true);
}
if (!$inst->downloadFile("var/backups/$dumpFile", "$destDumpDir/$dumpFile")) {
    error("Error occurred when downloading structure dump $dumpFile from " . $inst->name);
}

// remove backup on remote
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

// import
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
    error("Errors during data dump import, see $errorFileAbs");
}
unlink($errorFileAbs);
