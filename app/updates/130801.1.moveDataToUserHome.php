<?php

if (!file_exists(DATA_DIR . 'projects.json')) {
    $oldDataDir = APP_DIR . '../var/';
    if (file_exists($oldDataDir . 'projects.json')) {
        foreach (array('projects.json', 'allowedIPs') as $file) {
            if (!file_exists($oldDataDir . $file)) {
                continue;
            }
            if (!rename($oldDataDir . $file, DATA_DIR . $file)) {
                die('Failed to move data to home directory');
            }
        }
        exec("rm -rf $oldDataDir");
    }
}