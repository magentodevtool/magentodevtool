<?php

$list = \Projects\Local::getList();

foreach ($list as $projectName => $project) {
    if (!isset($project->installations)) {
        continue;
    }

    foreach ($project->installations as $installationName => $installation) {
        if ($installation->type == 'local') {
            continue;
        }

        if (isset($installation->serverIP)) {
            $installation->host = $installation->serverIP;
            unset($installation->serverIP);
        }
    }
}

Projects\Local::save($list);
