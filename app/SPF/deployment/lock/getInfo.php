<?php

#incspf deployment/lock

namespace SPF\deployment\lock;

use SPF\deployment\Lock;

function getInfo()
{
    $lock = new Lock();
    return $lock->getInfo();
}
