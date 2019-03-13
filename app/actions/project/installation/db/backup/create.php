<?php

$backupFileName = $ARG->fileName;

if (empty($ARG->tables)) {
    $ARG->tables = 'all';
}

if (empty($backupFileName) || strpos($backupFileName, '/') !== false) {
    error('Invalid file name');
}

return $inst->dump->create($backupFileName, $ARG->singleTransaction, $ARG->tables);
