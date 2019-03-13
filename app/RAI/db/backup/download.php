<?php

require_once __DIR__ . '/../../init.php';

if (!preg_match('~(local|alpha)~i', $instInfo->name)) {
    error('Allowed only on Local, Alpha');
}

$filePath = $_GET['fileName'];
$filePath = preg_replace('~(\.\.|\r|\n)~', '', $filePath);
$filePath = MAGE_ROOT . 'var/' . $_GET['fileName'];
if (!is_readable($filePath)) {
    error('File not found');
}

if (!file_exists($filePath)) {
    error('File not found');
}

header('Content-Encoding: none'); // disable web-server compression so that browser progress will show time left
header('Content-type: ' . mime_content_type($filePath));
header("Content-Disposition: attachment; filename=\"" . basename($filePath) . "\"");
header("Content-Length: " . filesize($filePath));

readfile($filePath);
