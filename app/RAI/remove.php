<?php

require_once 'init.php';

sleep($argv[1]);

$dir = __DIR__;
$parentDir = realpath($dir . '/..');

`rm -rf $dir`;
`rmdir $parentDir`;
