<?php

class Log
{

    static protected $actionLogId = null;
    static protected $connection = null;
    static protected $lifetimeDays = 90;

    static public function actionDispatchBefore($action)
    {

        if (!static::doLogAction($action)) {
            return;
        }

        $ARG = json_decode($_POST['ARG']);
        $underground = $_POST['underground'];

        static::logAction($ARG, $action, $underground);

        register_shutdown_function(array('Log', 'updateDuration'));

    }

    static function doLogAction($action)
    {
        $skip = [
            'project/installation/db/import/getProgressHtml' => 1,
        ];
        return !isset($skip[$action]);
    }

    static public function pageRenderBefore($view)
    {
        if ($view === 'projects') {
            static::cleanLogs();
        }
    }

    static public function logAction($ARG, $action, $underground)
    {
        $project = '';
        $installation = '';
        if (strpos($action, 'project/installation/') !== false) {
            $project = $ARG->installation->project->name;
            $installation = $ARG->installation->name;
            unset($ARG->installation);
        }

        static::getConnection()->insert('log_action', array(
            'datetime' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'user' => LDAP_USER,
            'project' => $project,
            'installation' => $installation,
            'action' => $action,
            'duration' => 0,
            'underground' => $underground,
        ));
        static::$actionLogId = static::getConnection()->lastInsertRowID();

        static::logActionArg($ARG);
    }

    static public function logActionArg($ARG)
    {
        static::getConnection()->insert('log_action_arg', array(
            'action_id' => static::$actionLogId,
            'arg' => json_encode($ARG, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ));
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

    static public function updateDuration()
    {
        $duration = static::getDuration();
        static::getConnection()->query("UPDATE log_action SET duration = " . $duration . " WHERE id = " . static::$actionLogId);
    }

    static function getDuration()
    {
        list($microTime, $time) = explode(' ', microtime());
        return ($time - START_TIME) + ($microTime - START_MICROTIME);
    }

    static function cleanLogs()
    {

        $maxExpiredId = static::getMaxExpiredId();
        if (!$maxExpiredId) {
            return null;
        }

        static::cleanLogActionArgs($maxExpiredId);
        static::cleanLogActions($maxExpiredId);

    }

    static function getMaxExpiredId()
    {
        $result = static::getConnection()->query("select max(id) as max_expired_id from log_action where datetime < date('now', '-" . static::$lifetimeDays . " day')")->fetchArray(SQLITE3_ASSOC);
        return $result['max_expired_id'];
    }

    static function cleanLogActionArgs($maxExpiredId)
    {
        static::getConnection()->query("DELETE FROM log_action_arg WHERE action_id <= " . $maxExpiredId);
    }

    static function cleanLogActions($maxExpiredId)
    {
        static::getConnection()->query("DELETE FROM log_action WHERE id <= " . $maxExpiredId);
    }

}
