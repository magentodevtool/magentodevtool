<?php

if ($nextFix = $inst->getNextFix()) {

    if (method_exists($inst, $nextFix)) {
        if ($inst->$nextFix()) {
            return array(
                'continue' => true
            );
        }
    }

    return array(
        'message' => template("project/installation/installer/$nextFix", array('inst' => $inst)),
    );
}

$inst->magento->flushCaches();

return array();