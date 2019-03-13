<?php

if (!is_object($ARG) || !isset($ARG->installation)) {
    die('You must to pass installation for this action');
}

$instData = $ARG->installation;
global $inst;
$inst = Projects::getInstallation($instData->source, $instData->project->name, $instData->name);

if (!$inst) {
    error(
        'Installation ' . var_export($instData->name, true)
        . ' for project ' . var_export($instData->project->name, true) . ' not found.'
    );
}

if (!Project::isInstAllowed($inst->type)) {
    error('Access denied to local installation from centralized devtool');
}

$ARG = $ARG->ARG;
