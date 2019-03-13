<?php

namespace SPF\mage;

function getM2EnvConfig()
{
    $m2EnvFile = 'app/etc/env.php';
    if (file_exists($m2EnvFile)) {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($m2EnvFile, true);
        }
        return include $m2EnvFile;
    }
    return false;
}