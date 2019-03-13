<?php

if (!isset($ARG->mode) || ($ARG->mode === 'specific' && !is_object($ARG->fixes))) {
    error('Invalid options');
}

if (!$inst->execOld('sudo pwd')) {
    error("You need sudo to perform this action.\nAsk admin to resolve problem");
}

if ($ARG->mode === 'specific') {
    if (!in_array(true, (array)$ARG->fixes)) {
        error('Please specify what to fix');
    }
}
$ARG->type = $inst->type;

return $inst->spf('mage/fixRights', $ARG);
