<?php

require_once __DIR__ . '/apache/functions.php';

if (trim(`whoami`) !== 'root') {
    die("Error: type \"sudo php apache.php\"\n");
}

cleanPreviousSetupV1();

// v1
//registerHost();
//configureDevToolApacheInstance();
//createDevToolVitrualHost();
//registerAutorun();
//runApacheDevToolInstance();

// v2
define('APACHE_INSTANCE_SUFFIX', 'devtool');
cleanPreviousSetupV2();
registerHost();
createSeparateApacheInstance();
configureApacheInstance();
registerAutorunV2();
runDevtool();

echo "Try http://devtool.local:81\n";
