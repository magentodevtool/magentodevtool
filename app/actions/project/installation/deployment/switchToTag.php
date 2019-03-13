<?php

$checkoutWarnings = array();
try {
    $inst->git->fetch();
    if ($deployment->mageVersion >= '2.2') {
        // reset config.php due to a M2.2 bug with spaces after setup:upgrade and app:config:dump
        $inst->exec('git checkout ./app/etc/config.php');
    }
    $checkoutWarnings = $inst->git->checkout($deployment->newTagName);
} catch (Exception $e) {
    deploymentDialog('failedToPullRemote', array('details' => $e->getMessage()));
}

if (count($checkoutWarnings)) {
    deploymentDialog('pullWarnings', array('details' => implode("\n", $checkoutWarnings)));
}
