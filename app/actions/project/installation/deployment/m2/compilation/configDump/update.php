<?php
/** @var \Project\Installation $inst */
/** @var \Project\Installation $localInst */
if (!$deployment->mage2->doCompileStaticContent) {
    return 1;
}

$configFile = $localInst->folder . 'app/etc/config.php';
$remoteConfig = $inst->magento->getConfigDump();
$config = $remoteConfig;

// restore merged modules section after dump
$localConfig = include $configFile;
$config['modules'] = $localConfig['modules'];

file_put_contents(
    $configFile,
    "<?php\nreturn " . var_export($config, true) . ";"
);
