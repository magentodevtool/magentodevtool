<?php

#incspf deployment/lock

namespace SPF\deployment\lock;

use SPF\deployment\Lock;

function capture($for)
{
    $lock = new Lock();
    return $lock->capture($for);
}
