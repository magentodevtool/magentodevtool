<?php

set_error_handler(function ($errno, $errstr) {
    throw new Exception($errstr, $errno);
});

function post($key, $default = NULL)
{
    $keys = explode("/", $key);
    $current = &$_REQUEST;
    foreach ($keys as $key) {
        if (!isset($current[$key])) return $default;
        $current = &$current[$key];
    }
    return $current;
}

function checked($key)
{
    if (post($key, false))
        return " checked";
    return "";
}