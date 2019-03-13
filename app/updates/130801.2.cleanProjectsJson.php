<?php

$projects = Projects\Local::getList();

foreach ($projects as $projectName => $project) {
    if (!isset($project->installations)) {
        continue;
    }
    foreach ($project->installations as $instName => $inst) {
        if ($inst->type === 'local') {
            if (isset($inst->login)) {
                unset($inst->login);
            }
            if (isset($inst->serverIP)) {
                unset($inst->serverIP);
            }
        }
        foreach ($inst as $propName => $prop) {
            if ($propName{0} === '_') {
                $varName = substr($propName, 1);
                if (!isset($inst->vars)) {
                    $inst->vars = new stdClass();
                }
                $inst->vars->$varName = $prop;
                unset($inst->$propName);
            }
        }
    }
}

Projects\Local::save($projects);
