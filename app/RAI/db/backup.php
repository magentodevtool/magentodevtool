<?php

require_once __DIR__ . '/../init.php';

if (!is_dir($backupsDir = MAGE_ROOT . 'var/backups/')) {
    umask(0);
    mkdir($backupsDir, 0775, true);
}

$singleTransaction = $argv[2];
$tables = json_decode($argv[3]);
$noData = $argv[4];
$backupFileName = ($tables == 'all') ? $argv[1] . '.sql' : $argv[1] . '.partial.sql';

$backupFilePath = $backupsDir . $backupFileName;
if (is_file($backupFilePath) || is_file($backupFilePath . '.gz')) {
    error("Backup already exists");
}

exec("ps aux | grep -i 'mysqldump' | grep -v 'grep' | awk '{print $2}'", $processIds);
if (count($processIds)) {
    error("Mysqldump is running now, please wait when it's finished.");
}

$backupFilePath .= '.gz';

$dumpScript = __DIR__ . '/backup/write.php';
$errorFile = $backupFilePath . '.errors';
$progressFile = $backupFilePath . '.progress';

exec('which pigz', $o, $r);
$gzipBinary = $r == 0 ? 'pigz' : 'gzip';

exec(
    cmd(
        "php %s %s %s %s %s %s | $gzipBinary > %s",
        $dumpScript, $errorFile, $progressFile, $singleTransaction, json_encode($tables), $noData, $backupFilePath
    ),
    $o, $r
);

if ($r !== 0) {
    error(implode("\n", $o));
}

$wereErrors = (bool)@filesize($errorFile);
if (!$wereErrors) {
    @unlink($errorFile);
}
@unlink($progressFile);

die(json_encode(array('wereErrors' => $wereErrors)));