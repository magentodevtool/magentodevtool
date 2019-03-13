<?php

# incspf db/getAppCredentials
# incspf db/getExternalCredentials

namespace SPF\db;

function getCredentials($type = 'external')
{
    if (!in_array($type, array('app', 'external'))) {
        \SPF\error('Invalid type passed to the ' . __FUNCTION__);
    }

    if ($type === 'external') {
        return getExternalCredentials();
    } else {
        return getAppCredentials();
    }
}
