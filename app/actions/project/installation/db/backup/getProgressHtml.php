<?php

$dir = $inst->_docRoot . 'var/backups/';
$fileName = $ARG->fileName;
$tables = $ARG->tables;
$progressFile = $dir . $fileName . ($tables !== 'all' ? '.partial' : '') . '.sql.gz.progress';

$progress = '';
try {
    // use exec to make working it on remote also
    $progress = 'dumping ' . $inst->exec('cat %s', $progressFile) . '..';
} catch (Exception $e) {
}

return html2text($progress);
