<?php

$connection = \SQLite::getDb('devtool');

$connection->exec("
	CREATE TABLE IF NOT EXISTS TEs (
	  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	  name varchar(250) NOT NULL,
	  details varchar(65536) NOT NULL
	);"
);
