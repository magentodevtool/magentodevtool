<?php
/** @var \Project\Installation $inst */

$containers = $inst->getDockerRunningContainers();

$containers = array_filter($containers, function ($container) {
    return strpos(implode(',', $container['names']), 'devtool') === false;
});

$ids = array_column($containers, 'id');

$result = $inst->spf('docker/stopContainers', $ids);

return $result !== false ? true : '<pre>' . html2text($inst->getDockerComposeServicesText()) . '</pre>';
