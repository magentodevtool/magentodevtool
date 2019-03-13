<?php

require_once 'init.php';

if (!$dbCreds = getDbCredentials()) {
    error('no db creds');
}
if (!$db = db($dbCreds)) {
    error('failed to connect db');
}
if (!$row = $db->query('select * from core_config_data where path = "web/unsecure/base_url" and scope_id = 0')->fetch()) {
    error('failed to fetch query');
}
$mainDomain = $row['value'];
$mainDomain = preg_replace('~^https?://~', '', $mainDomain);
$mainDomain = preg_replace('~/.*$~', '', $mainDomain);
if ($mainDomain{0} === '{') {
    $mainDomain = '';
}
die(json_encode($mainDomain));

