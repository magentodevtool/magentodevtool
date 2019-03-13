<?php

$ARG = json_decode($_POST['ARG']);
$id = $ARG->id;

$connection = \SQLite::getDb('devtool');
$sql = "select * from TEs2 where id = " . $connection->quote($id);
$result = $connection->query($sql);
$teRow = $result->fetchArray(SQLITE3_ASSOC);
if (!$teRow) {
    error('TE not found');
}

return $teRow['TE'];
