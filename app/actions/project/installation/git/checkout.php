<?php

// $ARG->doFlushCaches is not set for simple project type
$doFlushCaches = isset($ARG->doFlushCaches) ? $ARG->doFlushCaches : false;

$result = array(
    'applyChangesWarnings' => array(),
    'scssCompilationException' => null,
    'flushCaches' => true,
    'doFlushCaches' => $doFlushCaches,
);

// git fetch is required only if inst is remote
$inst->git->fetch();

if ($inst->generation->scss->isAvailable()) {
    $hashBefore = $inst->git->getCurrentHash();
}

$result['applyChangesWarnings'] = $inst->git->checkout($ARG->refName);

if ($inst->generation->scss->isAvailable()) {
    try {
        $hashAfter = $inst->git->getCurrentHash();
        $inst->generation->scss->runOnSourcesChange($hashBefore, $hashAfter);
    } catch (Exception $e) {
        $result['scssCompilationException'] = $e;
    }
}

if ($doFlushCaches) {
    $result['flushCaches'] = $inst->magento->flushCaches();
}

return $inst->form('git/sourcesChangeResult', $result);
