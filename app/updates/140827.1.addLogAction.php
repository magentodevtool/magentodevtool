<?php

namespace Projects;

$connection = \SQLite::getDb('devtool');

$connection->exec("
  CREATE TABLE IF NOT EXISTS log_action (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    datetime datetime NOT NULL,
    timezone varchar(50) NOT NULL,
    user varchar(50) NOT NULL,
    project varchar(50) NOT NULL,
    installation varchar(50) NOT NULL,
    action varchar(50) NOT NULL,
    duration decimal(12,4) NOT NULL
  );
");

$connection->exec("
  CREATE TABLE IF NOT EXISTS log_action_arg (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    action_id INTEGER NOT NULL,
    arg text NOT NULL
  );
");
