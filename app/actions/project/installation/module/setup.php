<?php

$modules = $ARG->modules;

if (!count($modules)) {
    error('Please select modules to setup');
}

$result = $inst->magento->module->setup->run($modules);

return $inst->form('module/setup/result', $result);
