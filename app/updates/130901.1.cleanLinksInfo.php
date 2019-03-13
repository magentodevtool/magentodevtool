<?php

$dbFile = DATA_DIR_INT . 'devtool.db';
if (!file_exists($dbFile)) {
    return;
}

$connection = new \SQLite3($dbFile);
$connection->exec("delete from vars where key = 'linksInfo'");
