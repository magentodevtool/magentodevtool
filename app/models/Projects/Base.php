<?php

namespace Projects;

class Base extends \Json
{

    protected static $file = 'projects.json';

    static function getList()
    {
        return parent::getData();
    }

    static function getInstallation($projectName, $name)
    {

        $list = static::getList();

        if (!isset($list->$projectName) || !isset($list->$projectName->installations->$name)) {
            return false;
        }

        $inst = clone $list->$projectName->installations->$name;
        $inst->name = $name;

        $project = clone $list->$projectName;
        unset($project->installations);
        $project->name = $projectName;
        $inst->project = $project;

        return new \Project\Installation($inst);

    }

    static function getByName($project)
    {
        return static::getList()->$project;
    }

    static function getAllProjectsTypes()
    {
        $projectTypes = array();
        foreach (static::getList() as $projectName => $projectData) {
            if (!isset($projectData->type)) {
                continue;
            }
            $projectTypes[$projectName] = $projectData->type;
        }
        return $projectTypes;
    }

}
