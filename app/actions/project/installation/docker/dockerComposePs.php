<?php
/** @var \Project\Installation $inst */
$html = $inst->getDockerComposeServicesText();

$html = preg_replace_callback('/(\d{1,4}\.\d{1,4}\.\d{1,4}\.\d{1,4}):(\d+)-/', function ($match) {
    list ($string, $ip, $port) = $match;
    if ($ip == '0.0.0.0') {
        $ip = 'localhost';
    }
    $protocol = $port == 443 ? 'https' : 'http';
    $url = "$protocol://$ip:$port";

    return "<a href=\"$url\" target=\"_blank\" rel=\"noopener\" onclick=\"
var otherWindow = window.open();
otherWindow.opener = null;
otherWindow.location = '{$url}';
return false;
\">$string</a>";
}, html2text($html));

return '<pre>' . $html . '</pre>';


