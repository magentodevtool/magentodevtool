<?php

if (!isset($ARG->teText) || !isset($ARG->childTeId)) {
    error('Not all required parameters are passed');
}

$teText = $ARG->teText;
$childTeId = $ARG->childTeId;
$teStorage = new \TimeEstimation\Storage();

$te1 = new TimeEstimation($teText);
$te2 = new TimeEstimation($teStorage->load($childTeId)->text);
$mergedTe = new TimeEstimation\Merged($te1, $te2);

return template(
    'timeEstimation/merged/result',
    compact('te1', 'te2', 'mergedTe', 'childTeId')
);