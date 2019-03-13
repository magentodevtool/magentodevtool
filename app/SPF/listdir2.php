<?php

namespace SPF;

function listdir2($dir, $options = array())
{
    $options = array_merge(
        array(
            'prependDir' => false,
            'recursive' => false,
            'entityRegexp' => null,
            'ignoreDirectories' => array(),
            'currPath' => '',
        ),
        $options
    );
    $dir = rtrim($dir, '/') . '/';
    if (!is_dir($dir)) {
        return array();
    }
    if (listdir2_doIgnoreDirectory($dir, $options)) {
        return array();
    }
    $currPath = $options['prependDir'] ? $dir : $options['currPath'];
    $currPath = $currPath !== '' ? rtrim($currPath, '/') . '/' : '';
    $files = array();
    foreach (scandir($dir) as $file) {
        if (in_array($file, array('.', '..'))) {
            continue;
        }
        $entity = $currPath . $file;
        if ($options['recursive'] && is_dir("$dir$file")) {
            if (listdir2_doIgnoreDirectory("$dir$file", $options)) {
                continue;
            }
            $childOptions = array_merge($options, array('prependDir' => false, 'currPath' => $entity . '/'));
            $files = array_merge($files, listdir2("$dir$file", $childOptions));
            continue;
        }
        if ($options['entityRegexp'] && !preg_match($options['entityRegexp'], $entity)) {
            continue;
        }
        $files[] = $entity;
    }
    return $files;
}

// no anonymous functions to be compatoble with php 5.3 (spar)
function listdir2_doIgnoreDirectory($dir, $options)
{
    $dir = rtrim($dir, '/') . '/';
    foreach ($options['ignoreDirectories'] as $ignoredDir) {
        $ignoredDir = rtrim($ignoredDir, '/') . '/';
        if ($dir === $ignoredDir) {
            return true;
        }
    }
    return false;
}
