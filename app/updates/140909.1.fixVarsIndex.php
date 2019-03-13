<?php

$connection = \SQLite::getDb('devtool');

$connection->query("DROP INDEX vars_multi_idx");
$connection->query("CREATE INDEX vars_multi_idx ON vars (user, project, installation, key)");
