<?php

$connection = \SQLite::getDb('devtool');

$connection->exec("
    CREATE INDEX `TEs_name_idx` ON `TEs` (name);
");
