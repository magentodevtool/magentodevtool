<?php

$dumpArchive = 'var/' . $dbImport->dump;

if (strpos($dumpArchive, '.gz') === false) {
    return array('success' => true);
}

$dumpFile = preg_replace('~\.gz$~', '', $dumpArchive);

try {
    $inst->exec('which pigz');
    $gzipBinary = 'pigz -d';
} catch (Exception $e) {
    $gzipBinary = 'gunzip';
}

$result = $inst->execOld("$gzipBinary -c %s > %s", $dumpArchive, $dumpFile);

if ($dbImport->rmArchiveAfterExtract) {
    unlink($inst->_appRoot . $dumpArchive);
}

if (!$result) {
    return array(
        'success' => false,
        'message' => 'Error: failed to extract dump (' . file_get_contents($inst->_appRoot . $dumpFile) . ')',
    );
}

return array('success' => true);
