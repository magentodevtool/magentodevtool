<?php

#incspf db

namespace SPF\db;

function search(
    $table_name_rx,
    $table_name_rx_cond,
    $field_name_rx,
    $field_name_rx_cond,
    $field_value_rx,
    $field_value_rx_cond
) {

    $tables = searchTables($table_name_rx, $table_name_rx_cond);
    $fields = searchFields($tables, $field_name_rx, $field_name_rx_cond);
    $values = searchValues($fields, $field_value_rx, $field_value_rx_cond);

    return compact('tables', 'fields', 'values');

}

function searchTables($table_name_rx, $table_name_rx_cond)
{
    $table_name_rx_php = '~' . str_replace('~', "\\~", $table_name_rx) . '~';
    $allTables = \SPF\db()->query('show tables')->fetchAll();
    $tables = array();
    foreach ($allTables as $table) {
        $table = $table[0];
        if ((bool)preg_match($table_name_rx_php, $table) === (bool)$table_name_rx_cond) {
            $tables[] = $table;
        }
    }
    return $tables;
}

function searchFields($tables, $field_name_rx, $field_name_rx_cond)
{
    $db = \SPF\db();
    $field_name_rx_php = '~' . str_replace('~', "\\~", $field_name_rx) . '~';
    $tablesFilterItems = $tables;
    foreach ($tablesFilterItems as &$item) {
        $item = $db->quote($item);
    }
    $tablesFilter = implode(",", $tablesFilterItems);
    $tablesFilter = $tablesFilter !== '' ? $tablesFilter : "''";
    $allFields = $db->query("
        SELECT TABLE_NAME, COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME IN ($tablesFilter) AND TABLE_SCHEMA = database()
    ")->fetchAll();
    $fields = array();
    foreach ($allFields as $field) {
        if ((bool)preg_match($field_name_rx_php, $field['COLUMN_NAME']) === (bool)$field_name_rx_cond) {
            $fields[] = array(
                'table' => $field['TABLE_NAME'],
                'name' => $field['COLUMN_NAME'],
            );
        }
    }
    return $fields;
}

function searchValues($fields, $field_value_rx, $field_value_rx_cond)
{
    if ($fields === false || $field_value_rx === '') {
        return false;
    }
    $db = \SPF\db();
    $values = array();
    $field_value_rx_mysql = $db->quote($field_value_rx);
    $cond = $field_value_rx_cond ? '' : 'not';
    foreach ($fields as $field) {
        $result = $db->query("
            select count(*) from `{$field['table']}`
            where `{$field['name']}` $cond regexp $field_value_rx_mysql
        ")->fetchAll();
        if ($count = $result[0][0]) {
            $values[] = array(
                'table' => $field['table'],
                'field' => $field['name'],
                'count' => $count,
            );
        }
    }
    return $values;
}

