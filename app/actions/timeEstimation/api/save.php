<?php

$ARG = json_decode($_POST['ARG']);
$name = $ARG->name;
$te = $ARG->te;
$user = $ARG->user;

$connection = \SQLite::getDb('devtool');
$connection->exec("
    insert into TEs2 (name, te, user, datetime) values(
        " . $connection->quote($name) . ",
        " . $connection->quote($te) . ",
        " . $connection->quote($user) . ",
        " . $connection->quote(date('Y-m-d H:i:s')) . "
    )"
);

return ['id' => $connection->lastInsertRowID()];