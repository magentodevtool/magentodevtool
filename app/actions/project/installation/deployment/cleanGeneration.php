<?php

if ($inst->project->type !== 'magento2') {
    return 1;
}
$m2Mode = $deployment->mage2->mode;

try {
    switch ($m2Mode) {
        case 'developer':
            $inst->exec('rm -rf var/view_preprocessed/* pub/static/*');
            break;
        case 'production':
            // clean files which are generated in pub/static but not pre-compiled otherwise they will stay obsolete if don't clean them
            // pub/static/_cache/ should be excluded to prevent broken pages until FPC flush
            // expected to be cleaned: */*/*/*/*/*/*/secure, */*/*/*/css/email-inline.min.css, */*/*/*/css/email.min.css
            if ($deployment->mageVersion < '2.2') {
                $inst->exec('git clean -fxd pub/static var/di var/generation var/view_preprocessed -e pub/static/_cache/');
            } else {
                $inst->exec('git clean -fxd pub/static generated/metadata generated/code var/view_preprocessed -e pub/static/_cache/');
            }
            break;
        default:
            error('Unsupported environment MAGE_MODE: ' . var_export($m2Mode, true));
            break;
    }
} catch (Exception $e) {
    deploymentDialog('failedToCleanGeneration', array('error' => $e->getMessage()));
}
