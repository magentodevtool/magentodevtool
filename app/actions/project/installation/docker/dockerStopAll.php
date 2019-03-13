<?php
/** @var \Project\Installation $inst */

$containers = $inst->getDockerRunningContainers();

$containers = array_filter($containers, function ($container) {
    return strpos(implode(',', $container['names']), 'devtool') === false;
});

$containers = array_column($containers, 'id');

if ($containers) {
    $result = $inst->spf('docker/stopContainers', $containers);

    $text = ($result === false) ? 'Could not stop containers' : "Stopped containers ID:\n$result";
} else {
    $text = 'No containers were running';
}

return '<pre>' . html2text($text) . '</pre>';


