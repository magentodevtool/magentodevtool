<?php

try {
    $inst->log->rotate();
} catch (Exception $e) {
    deploymentDialog('failedToRotateLogs', array('error' => $e->getMessage()));
}
