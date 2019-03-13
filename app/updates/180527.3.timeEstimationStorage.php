<?php

$connection = \SQLite::getDb('devtool');

$connection->exec("
    ALTER TABLE TEs ADD COLUMN user VARCHAR(50)
");

$connection->exec("
    ALTER TABLE TEs ADD COLUMN datetime datetime NULL
");

$connection->exec("
    CREATE INDEX TEs_user_idx ON TEs (user);
");

$connection->exec("
    CREATE INDEX TEs_datetime_idx ON TEs (datetime);
");
