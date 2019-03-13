<?php

namespace Projects;

$connection = \SQLite::getDb('devtool');

$connection->exec("
  CREATE INDEX log_action_datetime ON log_action (datetime);
");
