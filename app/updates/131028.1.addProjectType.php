<?php

$projects = Projects\Local::getList();

foreach ($projects as $projectName => $project) {
    if (isset($project->type)) {
        continue;
    }
    $newProject = new stdClass();
    $newProject->type = 'magento1';
    $projects->$projectName = (object)array_merge((array)$newProject, (array)$project);
}

Projects\Local::save($projects);
