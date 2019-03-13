<?php

$name = $ARG->name;
$details = $ARG->details;

if (trim($name) === '') {
    error('Fill-in the name');
}

if (trim($details) === '') {
    error('No details text was provided');
}

try {
    $teStorage = new \TimeEstimation\Storage();
    $link = $teStorage->save($name, json_encode(new TimeEstimation($details)));
} catch (\Exception $e) {
    error("Errors when TE saving: $e");
}

return $link;