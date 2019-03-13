<?php

$info = $inst->spf('db/backup/getInfo', "var/$ARG->fileName");

if (!isset($info->tables)) {
    error('No information found in dump file.');
}

// move backed up tables to the top + sort tables by name
$sortValues = array();
foreach ($info->tables as $tableName => $isBackedUp) {
    $sortValues[$tableName] = (int)!$isBackedUp . '|' . $tableName;
}
asort($sortValues);
$tablesSorted = new stdClass();
foreach ($sortValues as $tableName => $null) {
    $tablesSorted->$tableName = $info->tables->$tableName;
}
$info->tables = $tablesSorted;

return $inst->form('db/dump/info', compact('info'));
