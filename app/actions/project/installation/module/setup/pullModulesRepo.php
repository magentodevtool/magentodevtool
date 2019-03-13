<?php

$modulesInst = $inst->magento->module->setup->getModulesInstallation();
$currentBranch = $modulesInst->git->getCurrentBranch();
$modulesInst->exec('git pull origin %s', $currentBranch);
