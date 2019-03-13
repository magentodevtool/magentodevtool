<?php

$options = isset($ARG->options) ? $ARG->options : array();

$diff = $inst->git->getDiff('HEAD', $options, true);

return $inst->form('git/modifications/result', compact('diff'));
