<?php

$connection = \SQLite::getDb('devtool');

$connection->exec("
  CREATE INDEX vars_multi_idx ON vars (project, installation, key);
");
