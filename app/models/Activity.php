<?php

class Activity
{

    static public function register($project, $installation)
    {
        \SQLite::getDb('devtool')->query("
        insert into activity (project, installation, date)
          values (
            " . \SQLite::quote($project) . ",
            " . \SQLite::quote($installation) . ",
            " . \SQLite::quote(date('Y-m-d H:i:s')) . "
          )
        ");
    }

    static public function getPercent($project, $installation)
    {
        return static::getAllActivities()[$project][$installation]['percent'];
    }

    static public function getAllActivities()
    {

        static $activities;
        if (!is_null($activities)) {
            return $activities;
        }

        $db = \SQLite::getDb('devtool');

        // clean old info
        $db->query("
            delete from activity
            where date < " . \SQLite::quote(date('Y-m-d H:i:s', time() - 60 * 60 * 24 * 60))
        );

        // select grouped grouped counts
        $result = $db->query("
            select project, installation, count(id) as count from activity
            where date > " . \SQLite::quote(date('Y-m-d H:i:s', time() - 60 * 60 * 24 * 14)) . "
            group by project, installation
        ");

        // build result
        $activities = array();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $activities[$row['project']][$row['installation']]['count'] = $row['count'];
        }

        // calculate percent
        $projectList = object_clone_recursive(\Projects\Local::getList());
        $remoteProjectsList = new stdClass();
        try {
            $remoteProjectsList = \Projects\Remote::getList();
        } catch (\Exception $e) {
        }

        // merge local and remote projects
        foreach ($remoteProjectsList as $projectName => $project) {
            if (!isset($projectList->$projectName)) {
                $projectList->$projectName = $project;
            } else {
                if (!isset($project->installations)) {
                    continue;
                }
                if (!isset($projectList->$projectName->installations)) {
                    $projectList->$projectName->installations = new stdClass();
                }
                $projectList->$projectName->installations = (object)array_merge(
                    (array)$projectList->$projectName->installations,
                    (array)$project->installations
                );
            }
        }

        $min = PHP_INT_MAX;
        $max = 1;
        foreach ($projectList as $projectName => $project) {
            foreach ($project->installations as $installationName => $installation) {
                if (!isset($activities[$projectName][$installationName])) {
                    $min = 0;
                } else {
                    $max = 0;
                }
            }
        }
        foreach ($activities as $project => $installations) {
            foreach ($installations as $installation => $value) {
                if ($value['count'] < $min) {
                    $min = $value['count'];
                }
                if ($value['count'] > $max) {
                    $max = $value['count'];
                }
            }
        }
        foreach ($projectList as $projectName => $project) {
            foreach ($project->installations as $installationName => $installation) {
                $count = $min;
                if (isset($activities[$projectName]) && isset($activities[$projectName][$installationName])) {
                    $count = $activities[$projectName][$installationName]['count'];
                }
                $activities[$projectName][$installationName]['percent'] = ($count - $min) / ($max - $min) * 100;
            }
        }

        return $activities;

    }

}