<?php

$connection = \SQLite::getDb('devtool');

$connection->query("CREATE TEMPORARY TABLE vars_backup(id, project, installation, key, value)");
$connection->query("INSERT INTO vars_backup SELECT id, project, installation, key, value FROM vars");
$connection->query("DROP TABLE vars");

$connection->query("
    CREATE TABLE vars (
        id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        user varchar(50) NOT NULL,
        project varchar(50) NOT NULL,
        installation varchar(50) NOT NULL,
        key varchar(250) NOT NULL,
        value text NOT NULL)
");

$connection->query("INSERT INTO vars SELECT id, {$connection->quote(USER)}, project, installation, key, value FROM vars_backup");
$connection->query("DROP TABLE vars_backup");
