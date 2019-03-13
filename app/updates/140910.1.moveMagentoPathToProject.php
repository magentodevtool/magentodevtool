<?php

$projects = Projects\Local::getList();

foreach ($projects as $projectName => $project) {
    if (!isset($project->repository)) {
        continue;
    }
    if (!is_string($project->repository)) {
        continue;
    }

    $gitUrl = $project->repository;
    $project->repository = (object)array(
        'url' => $gitUrl,
    );

    if (!isset($project->installations)) {
        continue;
    }

    foreach ($project->installations as $inst) {
        if (!isset($inst->magentoPath)) {
            continue;
        }
        if ($project->type !== 'simple') {
            $project->repository->docRoot = $inst->magentoPath;
        }
        unset($inst->magentoPath);
    }
}

Projects\Local::save($projects);
