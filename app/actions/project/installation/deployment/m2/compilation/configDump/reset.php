<?php
/** @var \Project\Installation $localInst */
if (!$deployment->mage2->doCompileStaticContent) {
    return 1;
}

$localInst->exec('git checkout HEAD app/etc/config.php');
