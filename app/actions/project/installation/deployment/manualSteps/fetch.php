<?php

try {
    $localInst->git->fetch();
} catch (Exception $e) {
    deploymentDialog('failedToFetchLocal', array('details' => $e->getMessage()));
}
