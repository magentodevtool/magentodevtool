<?php

$connection = \SQLite::getDb('devtool');

$connection->exec("
	CREATE TABLE IF NOT EXISTS TEs2 (
	  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	  user VARCHAR(50),
	  datetime datetime NULL,
	  name varchar(250) NOT NULL,
	  TE varchar(65536) NOT NULL
	);"
);

$connection->exec("
    CREATE INDEX `TEs2_name_idx` ON `TEs2` (name);
");

$connection->exec("
    CREATE INDEX TEs2_user_idx ON TEs2 (user);
");

$connection->exec("
    CREATE INDEX TEs2_datetime_idx ON TEs2 (datetime);
");
