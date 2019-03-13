<?php

class SQLite
{

    /**
     * @param $database
     * @return bool|\SQLite\Connection
     */
    static function getDb($database)
    {
        if (!$db = new \SQLite\Connection(DATA_DIR_INT . $database . '.sqlite')) {
            return false;
        }

        // make set lock wait timeout not 0 (1 sec is not enough if HDD is slow and very loaded)
        $db->query('pragma busy_timeout = 20000');
        // disable rollback possibility and accelerate writes in 2 times
        $db->query("pragma journal_mode = off");
        // allow to save changes to HDD with some delay and accelerate writes in 1000 times
        $db->query("pragma synchronous = off");

        return $db;
    }

    /**
     * @deprecated please use '$connection->escapeString' method.
     */
    static function escape($string)
    {
        return \SQLite3::escapeString($string);
    }

    /**
     * @deprecated please use '$connection->quote' method.
     */
    static function quote($string)
    {
        return '"' . static::escape($string) . '"';
    }

}
