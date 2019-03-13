<?php

if (!$ARG || !is_object($ARG) || !isset($ARG->mode) || ($ARG->mode === 'specific' && !is_object($ARG->flush))) {
    error('Invalid options');
}

if ($ARG->mode === 'specific') {
    $emptyOptions = true;
    foreach ($ARG->flush as $cache) {
        if ($cache) {
            $emptyOptions = false;
            break;
        }
    }
    if ($emptyOptions) {
        error('Please specify what to flush');
    }
}

if (!$result = $inst->magento->flushCaches($ARG)) {
    return template('message/cacheFlushingFailed');
}

if (isset($result->error)) {
    error($result->error);
}

return template('message/cacheFlushingSuccess', (array)$result);