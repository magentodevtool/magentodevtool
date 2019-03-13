<?php

#incspf db
#incspf error

namespace SPF\db;

function getTablesColumns()
{
    if (!\SPF\db()) {
        \SPF\error('Connection to db failed');
    }

    $query = '
        SELECT TABLE_NAME, COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = database()
        ORDER BY TABLE_NAME, COLUMN_NAME;
    ';

    $tables = array();
    foreach (\SPF\db()->query($query)->fetchAll() as $row) {
        $tables[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
    }

    return $tables;
}
