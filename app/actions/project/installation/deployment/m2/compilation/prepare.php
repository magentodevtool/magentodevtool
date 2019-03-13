<?php

/** @var \Project\Installation $localInst */
$localInst->git->fetch();

$systemBranch = $deployment->currentRemoteBranch;
if ($systemBranch === 'master') {
    $systemBranch = 'Live';
}

$localInst->exec(
    [
        'git reset --hard',
        'git checkout %s',
        'git reset --hard %s',
    ],
    $systemBranch,
    "origin/$systemBranch"
);

if ($deployment->mageVersion >= '2.2') {
    $localInst->exec([
        'sudo rm -rf generated/code generated/metadata var/view_preprocessed var/cache pub/static/*',
    ]);
} else {
    $localInst->exec([
        'sudo rm -rf var/generation var/view_preprocessed var/cache var/di',
        'php bin/magento setup:upgrade --keep-generated', // sync themes
        'sudo rm -rf var/generation var/view_preprocessed var/cache var/di',
        'php bin/magento deploy:mode:set production --skip-compilation',
        'sudo rm -rf var/cache var/di var/generation var/view_preprocessed pub/static/*',
        'touch pub/static/deployed_version.txt', // workaround a bug of magento 2.1.1
    ]);
}

deploymentDialog('m2/compilation/progress', ['toDo' => getToDoList($localInst)]);
