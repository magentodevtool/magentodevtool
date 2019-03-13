<?php

# incspf db/getAppCredentials
# incspf docker/getExternalMysqlServerCredentials

namespace SPF\db;

function getExternalCredentials()
{
    global $instInfo;

    $appCred = getAppCredentials();

    if ($instInfo->webServer->type !== 'docker') {
        return $appCred;
    }

    $extCred = \SPF\docker\getExternalMysqlServerCredentials();
    // merge to add db name
    return (object)array_merge((array)$appCred, (array)$extCred);
}
