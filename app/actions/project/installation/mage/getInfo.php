<?php
$curProjectName = $inst->project->name;
$curInstName = $inst->name;

try {
    // refresh 'projects_info' cache
    if ($ARG->refresh || is_null(\Vars::get(null, null, null, "projects/info"))) {
        if (!$projectsInfo = \Vars::get(null, null, null, "projects/info")) {
            $projectsInfo = new stdClass();
        }
        $projectsInfo->list = new stdClass();
        $projectsInfo->date = date('Y-m-d H:i:s');
        \Vars::set(null, null, null, "projects/info", $projectsInfo);
    }

    $projectsInfo = \Vars::get(null, null, null, "projects/info");
    if (isset($projectsInfo->list->$curProjectName->$curInstName)) {
        return $projectsInfo->list->$curProjectName->$curInstName;
    }

    // add project info to cache
    $info = $inst->magento->getInfo($ARG->refresh);
    $projectsInfo->list->$curProjectName = new stdClass();
    $projectsInfo->list->$curProjectName->$curInstName = new stdClass();
    $projectsInfo->list->$curProjectName->$curInstName = $info;
    \Vars::set(null, null, null, "projects/info", $projectsInfo);

    return $info;
} catch (Exception $e) {
    $projectsInfo = \Vars::get(null, null, null, "projects/info");
    $projectsInfo->list->$curProjectName = new stdClass();
    $projectsInfo->list->$curProjectName->$curInstName = new stdClass();
    $projectsInfo->list->$curProjectName->$curInstName = $e->getMessage();
    \Vars::set(null, null, null, "projects/info", $projectsInfo);

    return $e->getMessage();
}
