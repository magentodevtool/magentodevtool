<?php

$backupFileName = $ARG->fileName;

if (empty($backupFileName) || strpos($backupFileName, '/') === 0) {
    error('Invalid file name');
}

$inst->exec('rm %s', 'var/' . $backupFileName);
