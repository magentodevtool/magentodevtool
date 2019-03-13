<?php

# incspf exec

namespace SPF\docker;

function stopContainers($ids = null)
{
    try {
        if (is_array($ids)) {
            array_unshift($ids, 'docker stop ' . str_repeat('%s ', count($ids)));
            return call_user_func_array('\SPF\exec', $ids);
        } else {
            return \SPF\exec('docker-compose stop');
        }

    } catch (\Exception $e) {
        return false;
    }
}
