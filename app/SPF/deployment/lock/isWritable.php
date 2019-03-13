<?php

#incspf deployment/lock

namespace SPF\deployment\lock;

use SPF\deployment\Lock;

function isWritable($hash)
{
    $lock = new Lock();
    return $lock->isWritable($hash);
}
