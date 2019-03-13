<?php

namespace Projects;

$connection = \SQLite::getDb('devtool');

$connection->exec("
  CREATE INDEX `activity_date_idx` ON `activity` (date);
");
