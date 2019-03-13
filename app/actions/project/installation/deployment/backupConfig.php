<?php

try {
    $comment = date('Y-m-d H:i:s');
    if (!empty($deployment->newTagComment)) {
        $comment .= ' (' . $deployment->newTagComment . ')';
    } elseif (count($deployment->branchesToDeploy)) {
        $comment .= ' (' . implode(', ', $deployment->branchesToDeploy) . ')';
    }
    $inst->config->backup($comment);
} catch (Exception $e) {
    deploymentDialog('failedToBackupConfig', array('error' => $e->getMessage()));
}
