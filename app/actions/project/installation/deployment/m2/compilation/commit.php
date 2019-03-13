<?php

/** @var \Project\Installation $localInst */

$systemBranch = $deployment->currentRemoteBranch;
if ($systemBranch === 'master') {
    $systemBranch = 'Live';
}

$foldersToCommit = [];
if ($deployment->mage2->doCompileDi) {
    $foldersToCommit = array_merge(
        $foldersToCommit,
        $deployment->mageVersion >= '2.2' ? ['generated/code', 'generated/metadata'] : ['var/di', 'var/generation']
    );
}
if ($deployment->mage2->doCompileStaticContent) {
    $foldersToCommit = array_merge($foldersToCommit, ['var/view_preprocessed', 'pub/static']);
}

$localInst->exec('git add -A -f ' . implode(' ', $foldersToCommit));

$diffToCommit = $localInst->exec("git diff HEAD --cached | head");
if (trim($diffToCommit) === '') {
    deploymentDialog('m2/compilation/nothingToCommit');
}

$commitComment = 'undefined';
if ($deployment->mage2->doCompileDi && $deployment->mage2->doCompileStaticContent) {
    $commitComment = 'compile di and static';
} elseif ($deployment->mage2->doCompileDi) {
    $commitComment = 'compile di';
} elseif ($deployment->mage2->doCompileStaticContent) {
    $commitComment = 'compile static content';
}


$localInst->exec(
    [
        'git commit -m %s',
        'git push origin %s:%s',
    ],
    $commitComment, $systemBranch, $systemBranch
);
