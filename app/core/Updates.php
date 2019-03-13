<?php

class Updates
{

    static function apply()
    {

        if (!static::lock()) {
            error('Devtool updating is in progress now. Please refresh page.');
        }
        $updatesInfo = static::getUpdatesInfo();
        $updates = static::getUpdates();

        foreach ($updates as $update) {
            if (!isset($updatesInfo->$update)) {
                require APP_DIR . 'updates/' . $update;
                $updatesInfo->$update = true;
                saveJson(DATA_DIR_INT . 'updatesInfo.json', $updatesInfo);
            }
        }

        static::unlock();

    }

    static function getUpdates()
    {
        $updates = array();
        $files = glob(APP_DIR . 'updates/*');
        foreach ($files as $file) {
            $updates[] = preg_replace('~^' . preg_quote(APP_DIR . 'updates/') . '~', '', $file);
        }
        usort($updates, 'version_compare');
        return (object)$updates;
    }

    static function getUpdatesInfo()
    {
        if (!file_exists(DATA_DIR_INT . 'updatesInfo.json')) {
            return new stdClass();
        }
        $info = file_get_contents(DATA_DIR_INT . 'updatesInfo.json');
        $info = json_decode($info);
        if (json_last_error() !== JSON_ERROR_NONE) {
            die("Can't load updates info");
        }
        return $info;
    }

    static function getLockFile()
    {
        return DATA_DIR_INT_LOCK . 'update.lock';
    }

    static function lock()
    {

        $attemptsLimit = 10;
        $attemptsDelaySec = 0.2;
        $lockAttempts = 0;

        // delete expired lock
        if ($mTime = @filemtime(static::getLockFile())) {
            if ((time() - $mTime) > 120) {
                static::unlock();
            }
        }

        while (!$fp = @fopen(static::getLockFile(), 'x')) {
            $lockAttempts++;
            usleep($attemptsDelaySec * 1000000);
            if ($lockAttempts > $attemptsLimit) {
                return false;
            }
        }

        fclose($fp);
        register_shutdown_function(array('Updates', 'unlock'));
        return true;

    }

    static function unlock()
    {
        if (file_exists(static::getLockFile())) {
            @unlink(static::getLockFile());
        }
    }

}
