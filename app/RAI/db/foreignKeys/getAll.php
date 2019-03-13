<?php

require_once __DIR__ . '/../../init.php';

if (!db(getDbCredentials())) {
    error('Connection to db failed');
}

die(json_encode(getForeignKeys()));
