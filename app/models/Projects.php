<?php

/**
 * Only for methods to work with both local and remote projects source
 */
class Projects
{

    static function getList($source)
    {
        if ($source == 'local') {
            return Projects\Local::getList();
        }
        return Projects\Remote::getList();
    }

    static function getInstallation($source, $projectName, $name)
    {
        $inst = false;
        if ($source === 'local') {
            $inst = Projects\Local::getInstallation($projectName, $name);
        } elseif ($source === 'remote') {
            $inst = Projects\Remote::getInstallation($projectName, $name);
        }
        if (is_object($inst)) {
            $inst->source = $source;
        }
        return $inst;
    }

    static function getByName($source, $project)
    {
        if ($source == 'local') {
            return Projects\Local::getByName($project);
        }
        return Projects\Remote::getByName($project);
    }

    static function getIssueLink($issueName)
    {
        if (!preg_match('~^[0-9A-Z]+\s*-\s*([0-9]+)~', $issueName, $ms)) {
            return false;
        }
        return "https://issuetracker/ticket?id=" . urlencode($ms[1]);
    }

    static public function validateInstallationName($name)
    {
        if (!preg_match_all('~local|alpha|beta|live~i', $name, $matches)) {
            error('Not valid installation name. It should contain one of local|alpha|beta|live');
        }
        return count($matches[0]) === 1;
    }

}
