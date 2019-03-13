<?php

if ($inst->project->type !== 'magento2') {
    return 1;
}

try {
    $inst->exec([
        'php bin/magento setup:upgrade --keep-generated',
    ]);
} catch (Exception $e) {
    deploymentDialog('failedToUpgradeModules', array('error' => $e->getMessage()));
}
