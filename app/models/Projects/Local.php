<?php

namespace Projects;

class Local extends Base
{

    static function addInstallation(\stdClass $data)
    {

        $projectName = $data->projectName;
        unset($data->projectName);
        $name = $data->name;
        unset($data->name);

        $list = static::getList();
        $list->$projectName->installations->$name = $data;
        parent::save($list);

    }

    static function add(\stdClass $data)
    {

        $list = static::getList();
        $projectName = $data->name;

        unset($data->name);
        $data->installations = new \stdClass();

        $list->$projectName = $data;
        parent::save($list);

    }

    static function updateInstallation($inst, $data)
    {

        $list = static::getList();
        foreach ($data as $k => $v) {
            $list->{$inst->project->name}->installations->{$inst->name}->$k = $v;
        }
        parent::save($list);

    }

}
