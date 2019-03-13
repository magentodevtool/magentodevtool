<?php

namespace Projects;

class Remote extends Base
{

    static protected $dir = null;
    static protected $repoUrl = null;

    static function getVisibleList()
    {

        $list = static::getSortedList();
        $options = static::getOptions();
        $visibleList = clone $list;

        foreach ($visibleList as $projectName => $project) {
            if (!$options->$projectName->visibility) {
                unset($visibleList->$projectName);
            }
        }

        return $visibleList;

    }

    static function getSortedList()
    {

        static $list;
        if (isset($list)) {
            return $list;
        }

        $list = static::getList();
        static::sortList($list);
        return $list;

    }

    static function getOptions()
    {

        static $options;
        if (isset($options)) {
            return $options;
        }

        $options = \Vars::get(LDAP_USER, null, null, 'projects/remote/options') ?: new \stdClass();

        $defaultOptions = (object)array(
            'visibility' => static::getOptionsDefault('visibility'),
            'position' => static::getOptionsDefault('position'),
        );

        foreach (static::getList() as $projectName => $project) {
            if (!isset($options->$projectName)) {
                $options->$projectName = $defaultOptions;
            }
        }

        return $options;

    }

    static function setOptions($value)
    {
        \Vars::set(LDAP_USER, null, null, 'projects/remote/options', $value);
    }

    static function sortList(&$list)
    {
        $options = static::getOptions();

        $sortArray = array();
        $i = 0;
        foreach ($list as $projectName => $data) {
            $sortArray[] = array(
                'projectName' => $projectName,
                'customPosition' => $options->$projectName->position,
                'jsonPosition' => $i++,
            );
        }

        usort($sortArray, function ($A, $B) {
            $a = $A['customPosition'];
            $b = $B['customPosition'];
            if ($a === $b) {
                $a = $A['jsonPosition'];
                $b = $B['jsonPosition'];
            }
            return $a === $b ? 0 : ($a < $b ? -1 : 1);
        });

        $sortedList = new \stdClass();
        foreach ($sortArray as $item) {
            $sortedList->{$item['projectName']} = $list->{$item['projectName']};
        }

        $list = $sortedList;

    }

    static function getOptionsDefault($propName)
    {
        $value = \Vars::get(LDAP_USER, null, null, "projects/remote/options/default/$propName");
        if (!is_null($value)) {
            return $value;
        }
        if ($propName === 'visibility') {
            return false;
        } elseif ($propName === 'position') {
            return 0;
        }
        return $value;
    }

    static function setOptionsDefault($propName, $value)
    {
        \Vars::set(LDAP_USER, null, null, "projects/remote/options/default/$propName", $value);
    }

    static function getDir()
    {
        if (!is_null(static::$dir)) {
            return static::$dir;
        }
        if (!$repoUrl = static::getRepoUrl()) {
            static::$dir = false;
            return static::$dir;
        }
        static::$dir = DATA_DIR_INT . 'remoteProjects/' . sha1($repoUrl) . '/';
        return static::$dir;
    }

    static function getRepoUrl()
    {

        if (!is_null(static::$repoUrl)) {
            return static::$repoUrl;
        }

        $config = \Config::getData();
        if (!isset($config->remoteProjectsSource)) {
            static::$repoUrl = false;
            return static::$repoUrl;
        }

        static::$repoUrl = $config->remoteProjectsSource;
        return static::$repoUrl;

    }

    static function cloneRepo()
    {

        $repoUrl = static::getRepoUrl();
        $dir = static::getDir();

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (!is_dir($dir . '.git')) {
            exec(cmd('git clone %s %s', $repoUrl, $dir), $output, $error);
            if ($error !== 0) {
                error("Remote projects clone failed:\n" . implode("\n", $output));
            }
        }

        return true;

    }

    static function pull()
    {

        static::cloneRepo();

        $dir = static::getDir();

        exec(cmd(array(
            'cd %s',
            'git pull'
        ), $dir), $output, $error);

        if ($error !== 0) {
            error("Remote projects pull failed:\n" . implode("\n", $output));
        }

        return true;

    }

    static function fetch()
    {

        $dir = static::getDir();

        exec(cmd(array(
            'cd %s',
            'git fetch'
        ), $dir), $output, $error);

        if ($error !== 0) {
            error("Remote projects fetch failed:\n" . implode("\n", $output));
        }

        return true;

    }

    static function isSynchronized()
    {
        if (!is_dir(static::getDir() . '.git')) {
            return false;
        }
        if (count(static::getCommitsBehind())) {
            return false;
        }
        static::fetch();
        return !count(static::getCommitsBehind());

    }

    static function getCommitsBehind()
    {
        exec(cmd(array('cd %s', 'git log --pretty=oneline master..origin/master'), static::getDir()), $output, $error);
        if ($error !== 0) {
            error("Remote projects status check failed:\n" . implode("\n", $output));
        }
        return $output;
    }

}
