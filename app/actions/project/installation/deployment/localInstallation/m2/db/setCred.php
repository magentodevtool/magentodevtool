<?php
/** @var \Project\Installation $localInst */
/** @var \stdClass $ARG */

$cred = $deployment->localInstallationDbCred;
foreach ($cred as $k => $v) {
    $cred->$k = trim($v);
}

if (
    !$localInst->setDbCredentials($cred)
    || !Mysql::server($cred = $localInst->getDbCredentials())
) {
    deploymentDialog('localInstallation/m2/db/credentials', compact('cred'));
}
