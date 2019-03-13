<?php

class Vars
{

    static protected $connection = null;

    static function set($user, $project, $installation, $key, $value)
    {

        $connection = static::getConnection();

        $value = json_encode($value);
        if ($var = static::getData($user, $project, $installation, $key)) {
            $sql = "UPDATE vars SET value = " . $connection->quote($value) . " WHERE id = " . $connection->quote($var['id']);
            $connection->query($sql);
        } else {
            $connection->insert('vars', array(
                'user' => $user,
                'project' => $project,
                'installation' => $installation,
                'key' => $key,
                'value' => $value,
            ));
        }

    }

    static function get($user, $project, $installation, $key)
    {
        $var = static::getData($user, $project, $installation, $key);
        if (!$var) {
            return null;
        }
        return json_decode($var['value']);
    }

    static function delete($user, $project, $installation, $key)
    {

        $connection = static::getConnection();

        $sql = "
            DELETE from vars where
                user = " . $connection->quote($user) . "
                AND project = " . $connection->quote($project) . "
                AND installation = " . $connection->quote($installation) . "
                AND key = " . $connection->quote($key) . ";";
        $connection->query($sql);

    }

    static protected function getData($user, $project, $installation, $key)
    {

        $connection = static::getConnection();

        $sql = "
            SELECT * FROM vars WHERE
                user = " . $connection->quote($user) . "
                AND project = " . $connection->quote($project) . "
                AND installation = " . $connection->quote($installation) . "
                AND key = " . $connection->quote($key);
        $result = $connection->query($sql);

        $data = $result->fetchArray(SQLITE3_ASSOC);

        return $data;

    }

    /**
     * @return SQLite\Connection
     */
    static public function getConnection()
    {
        if (!is_null(static::$connection)) {
            return static::$connection;
        }
        static::$connection = \SQLite::getDb('devtool');
        return static::$connection;
    }

}
