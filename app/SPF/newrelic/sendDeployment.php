<?php

# incspf exec
# incspf error

namespace SPF\newrelic;

function sendDeployment($deploymentEvent)
{
    if (!is_array($deploymentEvent['deployment'])) {
        \SPF\error("You should set array deployment variable for NewRelic.");
    }

    if (!isset($deploymentEvent['x-api-key'])) {
        \SPF\error("You should set x-api-key NewRelic variable.");
    }

    if (!isset($deploymentEvent['deployment']['app_name']) || empty($deploymentEvent['deployment']['app_name'])) {
        \SPF\error("You should set appname NewRelic variable.");
    }

    $deployment = $deploymentEvent['deployment'];
    $xApiKey = $deploymentEvent['x-api-key'];

    $cmd = "curl -s -S -H " . escapeshellarg("x-api-key:{$xApiKey}");
    foreach ($deployment as $key => $value) {
        if (strlen($value) > 0) {
            $cmd .= " -d " . escapeshellarg("deployment[{$key}]={$value}");
        }
    }
    $cmd .= " " . escapeshellarg("https://rpm.newrelic.com/deployments.xml");
    $output = \SPF\exec($cmd);
    $response = strstr($output, '<?xml');
    $error = ($response === false) ? 'response is not in xml format.' : '';

    return (object)array(
        'success' => (strlen($error) > 0 ? false : true),
        'appname' => $deployment['app_name'],
        'response' => $response,
        'error' => $error,
    );
}
