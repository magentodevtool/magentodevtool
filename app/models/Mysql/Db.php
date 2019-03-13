<?php

namespace Mysql;

class Db
{

    static function tablesCount()
    {
        return \Mysql::query('show tables')->rowCount();
    }

    static function tableExists($tableName)
    {
        return (bool)\Mysql::query("show tables like " . \Mysql::quote($tableName))->rowCount();
    }

    static function isMagento()
    {
        return (static::tablesCount() > 200) && static::tableExists('core_config_data');
    }

}