<?php

$tagName = trim($ARG->name);
$tagComment = trim($ARG->comment);

if ($tagName == '' || $tagComment == '') {
    error('Please fill-in name and comment');
}

$inst->git->createTag($tagName, $tagComment);
