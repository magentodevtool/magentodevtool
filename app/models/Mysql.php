<?php

class Mysql
{

    static function server($credentials = null)
    {
        static $pdo = null;
        if (is_null($pdo) && is_null($credentials)) {
            return false;
        }
        if (!is_null($credentials)) {
            try {
                $pdo = new \PDO(
                    'mysql:host=' . $credentials->host . ';port=' . $credentials->port,
                    $credentials->username,
                    $credentials->password
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (\Exception $e) {
                return false;
            }
        }
        return $pdo;
    }

    static function query($query, $args = array())
    {
        if (!static::server()) {
            return false;
        }
        $stm = static::server()->prepare($query);
        $stm->execute($args);
        return $stm;
    }

    /**
     * @deprecated because it's used as both dbExists and useDb
     * so you need to use (or create if doesn't exist yet) dbExists or useDb function(s),
     * useDb can throw exception but dbExists can't
     */
    static function db($dbName)
    {
        try {
            return (bool)static::query("use " . static::quoteDbName($dbName));
        } catch (Exception $e) {
            return false;
        }
    }

    static function dbExists($dbName)
    {
        try {
            return (bool)static::query("use " . static::quoteDbName($dbName));
        } catch (Exception $e) {
            return false;
        }
    }

    static function useDb($dbName)
    {
        static::query("use " . static::quoteDbName($dbName));
    }

    static function createDb($dbname)
    {
        static::query("create database " . static::quoteDbName($dbname));
    }

    static function dropDb($dbname)
    {
        static::query("drop database " . static::quoteDbName($dbname));
    }

    static function quoteDbName($dbname)
    {
        return '`' . str_replace(array('`', "\n", "\r"), '', $dbname) . '`';
    }

    static function quote($string)
    {
        return static::server()->quote($string);
    }

}