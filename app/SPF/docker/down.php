<?php

# incspf exec

namespace SPF\docker;

function down()
{
    try {
        return \SPF\exec('docker-compose down --rmi local -v --remove-orphans');
    } catch (\Exception $e) {
        return false;
    }
}
