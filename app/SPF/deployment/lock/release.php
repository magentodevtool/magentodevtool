<?php

#incspf deployment/lock

namespace SPF\deployment\lock;

use SPF\deployment\Lock;

function release($hash)
{
    $lock = new Lock();
    return $lock->release($hash);
}
