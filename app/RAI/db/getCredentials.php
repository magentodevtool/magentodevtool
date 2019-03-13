<?php

require_once __DIR__ . '/../init.php';

if (!$cred = getDbCredentials()) {
    error("Database credentials not found");
}

die(json_encode($cred));