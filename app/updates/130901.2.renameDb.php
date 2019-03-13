<?php

$oldName = DATA_DIR_INT . 'devtool.db';
$newName = DATA_DIR_INT . 'devtool.sqlite';

if (file_exists($oldName)) {
    rename($oldName, $newName);
}
