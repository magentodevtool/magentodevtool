<?php

#incspf error
#incspf maintenance/removeInjection
#incspf maintenance/getMaintenanceFile

namespace SPF\maintenance;

function turnOff()
{
    $content = file_get_contents('index.php');
    removeInjection($content);
    if (!file_put_contents('index.php', $content)) {
        \SPF\error('Unable to modify index.php');
    };

    if (file_exists(getMaintenanceFile()) && !unlink(getMaintenanceFile())) {
        \SPF\error('Unable to remove ' . getMaintenanceFile());
    };
}