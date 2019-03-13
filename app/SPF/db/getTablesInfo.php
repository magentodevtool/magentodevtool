<?php

#incspf db

namespace SPF\db;

function getTablesInfo($orderBy = 'size desc')
{
    if (!\SPF\db()) {
        return (object)array('error' => 'Connection to db failed');
    }

    $query = '
        SELECT table_name AS "table",
        round(((data_length + index_length) / 1024 / 1024), 2) "size"
        FROM information_schema.TABLES
        WHERE table_schema = database()
    ';

    if ($orderBy) {
        $query .= ' Order by ' . $orderBy;
    }

    $tables = array();
    foreach (\SPF\db()->query($query)->fetchAll() as $row) {
        $tables[$row['table']] = new \stdClass();
        $tables[$row['table']]->size = $row['size'];
    }

    return $tables;
}
