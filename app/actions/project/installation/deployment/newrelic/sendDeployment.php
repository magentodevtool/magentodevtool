<?php

$result = array(
    'response' => null,
    'config' => null,
    'exception' => false,
);

try {
    $config = $inst->newrelic->getConfig();
    $result['config'] = $config;
    if ($config['enabled']) {
        $desc = $inst->newrelic->getDeploymentDescription($deployment);
        $result['response'] = (array)$inst->newrelic->sendDeployment($desc->tag, $desc->text);
    }
} catch (Exception $e) {
    $result['exception'] = $e->getMessage();
}

deploymentDialog('newrelic/result', $result);
