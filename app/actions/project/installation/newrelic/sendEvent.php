<?php

$inst->vars->set('lastNewRelicMsg', $ARG->consoleText);

$config = $inst->newrelic->getConfig();

$response = $config['enabled'] ?
    $inst->newrelic->sendDeployment("", $ARG->consoleText) :
    (object)array('success' => false, 'available' => false, 'error' => 'not available');

return isset($response->newrelicOutput) ? $response->newrelicOutput : $response->error;