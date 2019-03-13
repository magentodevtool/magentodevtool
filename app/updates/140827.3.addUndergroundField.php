<?php

namespace Projects;

$connection = \SQLite::getDb('devtool');

$connection->exec("
  ALTER TABLE log_action ADD COLUMN underground TINYINT;
");
