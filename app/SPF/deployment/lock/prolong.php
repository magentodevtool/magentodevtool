<?php

#incspf deployment/lock

namespace SPF\deployment\lock;

use SPF\deployment\Lock;

function prolong($hash)
{
    $lock = new Lock();
    return $lock->prolong($hash);
}
