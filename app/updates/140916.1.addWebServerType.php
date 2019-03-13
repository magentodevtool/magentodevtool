<?php

$list = \Projects\Local::getList();

foreach ($list as $projectName => $project) {
    foreach ($project->installations as $installationName => $installation) {
        if ($installation->type != 'local') {
            continue;
        }

        $localInstallation = Projects\Local::getInstallation($projectName, $installationName);

        if (isset($installation->serverType)) {
            $localInstallation->setWebServerType($installation->serverType);
            unset($list->$projectName->installations->$installationName->serverType);
        }
    }
}

Projects\Local::save($list);
