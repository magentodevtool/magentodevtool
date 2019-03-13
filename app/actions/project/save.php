<?php

$list = Projects\Local::getList();

if (empty($ARG->name) || empty($ARG->repository->url) || ($ARG->type !== 'simple' && empty($ARG->repository->docRoot))) {
    error('Fill-in data');
}

if (isset($list->{$ARG->name})) {
    error('Project already exists');
}

Projects\Local::add($ARG);
