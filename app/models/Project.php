<?php

/**
 * Class Project
 *
 * @property string $type
 * @property object $repository
 * @property string $name
 */
class Project
{

    static function getRemoteInstallations($source, $project)
    {
        $result = new \stdClass();
        $list = Projects::getList($source);
        if (isset($list->$project)) {
            $installations = $list->$project->installations;
            foreach ($installations as $name => $prop) {
                if ($prop->type == 'remote') {
                    $result->$name = $prop;
                }
            }
        }
        return $result;
    }

    static function getLocalInstallation($source, $project)
    {
        $list = Projects::getList($source);
        if (isset($list->$project)) {
            $installations = $list->$project->installations;
            foreach ($installations as $name => $prop) {
                if ($prop->type == 'local') {
                    return \Projects::getInstallation($source, $project, $name);
                }
            }
        }
        return false;
    }

    static function getClosestInstallation($inst)
    {
        if ($inst->inst()->type === 'local') {
            return $inst;
        }
        if (!$closestInst = \Project::getLocalInstallation($inst->source, $inst->inst()->project->name)) {
            return $inst;
        }
        if ($closestInst->type === 'local' && !$closestInst->inst()->checkRepo()) {
            return $inst;
        }
        return $closestInst;
    }

    static function isInstAllowed($instType)
    {
        if (Config::getNode('isCentralized')) {
            return $instType !== 'local';
        }

        return true;
    }

}