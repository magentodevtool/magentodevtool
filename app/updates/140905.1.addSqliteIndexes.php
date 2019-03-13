<?php

$connection = \SQLite::getDb('devtool');

$connection->exec("
  CREATE INDEX log_action_arg_action_id_idx ON log_action_arg (action_id);
");
