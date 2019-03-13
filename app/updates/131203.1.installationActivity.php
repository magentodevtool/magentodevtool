<?php

namespace Projects;

$connection = \SQLite::getDb('devtool');

$connection->exec("
  CREATE TABLE IF NOT EXISTS activity (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    project varchar(50) NOT NULL,
    installation varchar(50) NOT NULL,
    date date NOT NULL
  );
");
