<?php

if (empty($ARG->codePool) || empty($ARG->folder) || empty($ARG->module)) {
    error('Please provide code pool, module name and destination to export.');
}

return $inst->magento->module->export->run($ARG);
