<?php

# incspf error

namespace SPF;

function exec()
{

    $args = func_get_args();
    if (!count($args)) {
        return false;
    }
    $cmd = array_shift($args);
    if (is_array($cmd)) {
        $cmd = implode(' 2>&1 && ', $cmd);
    }
    $cmd .= ' 2>&1';
    if (count($args)) {
        foreach ($args as &$arg) {
            $arg = escapeshellarg($arg);
        }
        array_unshift($args, $cmd);
        $cmd = call_user_func_array('sprintf', $args);
    }

    \exec($cmd, $o, $r);
    $o = implode("\n", $o);
    if ($r === 0) {
        return $o;
    } else {
        error("exec error for \"$cmd\":\n$o");
    }

}
