<?php

if (!count($deployment->branchesToDeploy)) {
    error('Please select at least one branch');
}

if ($deployment->type === 'production') {
    if (empty($deployment->newTagName)) {
        error('Please provide new tag name');
    }
    if (empty($deployment->newTagComment)) {
        error('Please provide new tag comment');
    }
}

if ($deployment->mageVersion >= '2.2') {
    // M2.2+ deployment will run "php bin/magento app:config:dump" for static compilations which will not work without vendor
    // vendor folder is empty when first deployment
    if (
        isset($deployment->mage2->doCompileStaticContent)
        && $deployment->mage2->doCompileStaticContent
        && !$remoteInst->spf('composer/isBuilt')
    ) {
        error('"vendor" folder isn\'t built on remote, you need to make first deployment without static content compilation');
    }
}

if (!$deployment->isConfirmed) {
    deploymentDialog('unconfirmed');
}
