<?php

$text = $ARG->consoleText;
$te = new TimeEstimation($text);

return template('timeEstimation/result', compact('te'));
