<?php

require_once __DIR__ . '/../../init.php';

$file = $argv[1];
if (!file_exists($file)) {
    error('File not found');
}

$tablesArg = json_decode($argv[2]);
$tables = is_array($tablesArg) ? array_fill_keys($tablesArg, null) : $tablesArg;
$database = $argv[3];
$fh = fopen($file, 'r');

$transaction = false;
$transactionSize = 0;
$transactionMaxSize = 6 * 1024 * 1024;

while (($line = fgets($fh)) !== false) {

    if (preg_match('~^mysqldump: ~', $line)) {
        // skip warningns
        continue;
    }

    $isInsert = preg_match('~^INSERT INTO `([^`]+)~i', $line, $insertMatches);

    // Skip insert statements for unselected tables
    if (is_array($tables)) {
        if ($isInsert) {
            $tableName = $insertMatches[1];
            if (!array_key_exists($tableName, $tables)) {
                continue;
            }
        }
    } elseif ($tables === 'all') {
        // nothing to skip
    } else {
        error('Unsupported argument value for "tables"');
    }

    if (preg_match('~^.{1,50}DEFINER=`[^`]+`@`[^`]+`~i', $line)) {
        $line = preg_replace('~ DEFINER=`[^`]+`@`[^`]+`~i', '', $line);
    }

    $line = preg_replace('~^ALTER DATABASE [^\s]+ ~i', "ALTER DATABASE `$database` ", $line);

    // Wrap insert statements into transaction because sometimes sysadmins
    // provide DB dump with single INSERT statements which are very slow
    if ($transaction && $isInsert) {
        $transactionSize += strlen($line);
    }
    if ($transaction && (!$isInsert || ($transactionSize >= $transactionMaxSize))) {
        echo "CoMmIt;\n"; // CoMmIt: shaking case is to recognize these additions when progress calculation
        $transaction = false;
        $transactionSize = 0;
    }
    if (!$transaction && $isInsert) {
        echo "StArT TRANSACTION;\n";
        $transaction = true;
    }

    echo $line;
}

fclose($fh);

if ($transaction) {
    echo "\nCoMmIt;\n";
}
