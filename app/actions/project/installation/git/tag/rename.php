<?php

$tag = $ARG->tag;
$newName = trim($ARG->newName);

if ($newName === '') {
    error('Invalid tag name');
}

$tags = $inst->git->getTags();
if (isset($tags[$newName])) {
    error('Tag already exists');
}

if (!$inst->execOld(
    array(
        'git tag %s %s',
        'git tag -d %s'
    ), $newName, $tag->name, $tag->name)
) {
    error($inst->execOutput);
}

if (!$tag->local) {
    $result = $inst->execOld(
        array(
            'git push origin :%s',
            'git push origin %s'
        ), $tag->name, $newName
    );
    if (!$result) {
        error($inst->execOutput);
    }
}