<?php

#incspf listdir

namespace SPF;

function getDirSizeInfo($dir, $secondsLimit = 0.2, $excludeRegexp = false, $excludeRegexpMods = 'i')
{

    $info = array('bytes' => 0, 'wasTimeout' => false);

    if (!is_dir($dir)) {
        return $info;
    }

    list($startMicroTime, $startTime) = explode(' ', microtime());

    $secondsLeft = function () use ($secondsLimit, $startTime, $startMicroTime) {
        list($microTime, $time) = explode(' ', microtime());
        return $secondsLimit - (($time - $startTime) + ($microTime - $startMicroTime));
    };

    $dirResource = opendir($dir);
    while (($item = readdir($dirResource)) !== false) {

        if ($secondsLimit !== false && $secondsLeft() <= 0) {
            $info['wasTimeout'] = true;
            break;
        }

        if (in_array($item, array('.', '..'))) {
            continue;
        }

        $item = "$dir/$item";

        if ($excludeRegexp) {
            $excludeRegexpEscaped = str_replace('~', '\\~', $excludeRegexp);
            // $excludeRegexpMods is passed separately in order to make regexp more secure (better control of "e" modifier)
            if (preg_match("~$excludeRegexpEscaped~$excludeRegexpMods", $item)) {
                continue;
            }
        }

        if (is_dir($item)) {
            $itemSecondsLimit = $secondsLimit === false ? false : $secondsLeft();
            $itemSizeInfo = getDirSizeInfo($item, $itemSecondsLimit, $excludeRegexp, $excludeRegexpMods);
            $info['bytes'] += $itemSizeInfo['bytes'];
            $info['wasTimeout'] = $itemSizeInfo['wasTimeout'];
            if ($info['wasTimeout']) {
                break;
            }
        } else {
            $info['bytes'] += filesize($item);
        }

    }

    closedir($dirResource);
    return $info;

}
