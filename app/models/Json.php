<?php

abstract class Json
{

    protected static $dir = DATA_DIR;
    protected static $file = null;
    protected static $baseClass = 'Json\\Node';

    protected static $cache = array();

    static function getData()
    {

        $file = static::getFileAbsPath();

        if (isset(static::$cache[$file])) {
            return static::$cache[$file];
        }

        if (file_exists($file)) {
            $json = json_decode(file_get_contents($file));
            if (!$json instanceof \stdClass) {
                error("Error: Can't parse " . static::$file . ", check syntax");
            }
        } else {
            $json = new \stdClass();
        }

        $object = object_rebuild_recursive($json, static::$baseClass);

        static::$cache[$file] = $object;

        return $object;

    }

    static function save($data)
    {

        $file = static::getFileAbsPath();

        saveJson($file, $data);

        static::$cache[$file] = null;

    }

    static function getFileAbsPath()
    {
        return static::getDir() . static::$file;
    }

    static function getDir()
    {
        return static::$dir;
    }

    static function getNode($path)
    {
        return static::getData()->getNode($path);
    }

    static function setNode($path, $value)
    {
        static::getData()->setNode($path, $value);
    }

}
