<?php

Projects::validateInstallationName($ARG->name);

$list = Projects\Local::getList();

$required = array('projectName', 'name', 'type', 'folder');
if ($ARG->type == 'remote') {
    $required[] = 'login';
    $required[] = 'host';
}
if (preg_match('~magento~', $list->{$ARG->projectName}->type)) {
    $required[] = 'domain';
}

if (validateRequiredFields($required, $ARG) !== true) {
    error('Fill-in data');
}


if (isset($list->{$ARG->projectName}->installations->{$ARG->name})) {
    error('Installation already exists');
}

Projects\Local::addInstallation($ARG);
