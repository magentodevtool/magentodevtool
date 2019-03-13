<?php
/** @var \Project\Installation $localInst */

if ($inst->project->type !== 'magento2' || $deployment->mageVersion > '2.2') {
    return 1;
}

$localVersion = $localInst->magento->getInfo(true)->version;
if ($localVersion === 'Undefined') {
    error('Can\'t determine Magento version in ' . $localInst->folder);
}
if ($localVersion < '2.2') {
    return 1;
}

deploymentDialog('notCompatibleDeployment');
