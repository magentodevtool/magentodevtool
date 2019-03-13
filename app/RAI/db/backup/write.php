<?php

require_once __DIR__ . '/../../init.php';

if (!$cred = getDbCredentials()) {
    error("Database credentials not found");
}
$errorFile = $argv[1];
$progressFile = $argv[2];
$singleTransaction = ($argv[3] == 'enableST') ? '--single-transaction' : '';
$tables = ($argv[4]) ? json_decode($argv[4]) : 'all';
$noData = ($argv[5] == 'withoutData') ? '--no-data' : '';
$fp = fopen($progressFile, 'w');

if ($tables == 'all') {
    $dumpInfo = false;
    $mysqlTablesArg = '';
} else {
    if (!is_object($tables)) {
        error('Not correct table parameter');
    }
    $tables = get_object_vars($tables);
    asort($tables);
    $dumpInfo = array(
        'tables' => $tables
    );

    $mysqlTablesArg = array();
    foreach ($tables as $tableName => $tableValue) {
        if ($tableValue) {
            $mysqlTablesArg[] = $tableName;
        }
    }
    $mysqlTablesArg = implode(' ', $mysqlTablesArg);
}

execCallback(
    cmd(
        'MYSQL_PWD=%s mysqldump ' . $singleTransaction . ' ' . $noData . ' --force --log-error %s --routines -h%s -u%s %s ' . $mysqlTablesArg,
        $cred->password, $errorFile, $cred->host, $cred->username, $cred->dbname
    ),
    function ($chunk, $isNewLine) use ($fp, $dumpInfo) {
        static $isInfoPlaced = false;
        if (!$isInfoPlaced && $dumpInfo) {
            echo "/* Devtool dump info:\n"
                . json_encode(
                    $dumpInfo,
                    (PHP_MAJOR_VERSION * 10 + PHP_MINOR_VERSION) >= 54 ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : 0
                )
                . "\nEnd devtool dump info */ \n\n";
            $isInfoPlaced = true;
        }

        if ($isNewLine) {
            if (preg_match('~^CREATE TABLE `(.+)` ~i', $chunk, $ms)) {
                fseek($fp, 0);
                fwrite($fp, $ms[1] . str_repeat(' ', 255));
            } else {
                if (preg_match('~^.{1,50}DEFINER=`[^`]+`@`[^`]+`~i', $chunk)) {
                    $chunk = preg_replace('~ DEFINER=`[^`]+`@`[^`]+`~i', '', $chunk);
                }
            }
        }
        echo $chunk;
    },
    cpuCoresCount() > 1 ? 0 : 500
);

fclose($fp);
