<?php

namespace SPF;

function listdir($dir, $prependDir = false, $recursive = false, $entityRegexp = null, $currPath = '')
{
    $dir = rtrim($dir, '/') . '/';
    if (!is_dir($dir)) {
        return array();
    }
    $currPath = $prependDir ? $dir : $currPath;
    $currPath = $currPath !== '' ? rtrim($currPath, '/') . '/' : '';
    $files = array();
    foreach (scandir($dir) as $file) {
        if (in_array($file, array('.', '..'))) {
            continue;
        }
        $entity = $currPath . $file;
        if ($recursive && is_dir("$dir$file")) {
            $files = array_merge($files, listdir("$dir$file", false, true, $entityRegexp, $entity . '/'));
            continue;
        }
        if ($entityRegexp && !preg_match($entityRegexp, $entity)) {
            continue;
        }
        $files[] = $entity;
    }
    return $files;
}
