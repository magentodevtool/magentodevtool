<?php

#incspf error
#incspf maintenance/removeInjection
#incspf maintenance/getMaintenanceKey
#incspf maintenance/getInjectionText
#incspf maintenance/getMaintenanceFile

namespace SPF\maintenance;

function turnOn($allowedIPs)
{
    $injection = getInjectionText($allowedIPs);
    if (!file_put_contents(getMaintenanceFile(), $injection)) {
        \SPF\error('Unable to create ' . getMaintenanceFile());
    };

    $content = file_get_contents('index.php');
    removeInjection($content);

    if (!file_put_contents(
        'index.php',
        "<?php " . getMaintenanceKey() . " include('" . getMaintenanceFile() . "');?>" . $content)
    ) {
        \SPF\error('Unable to modify index.php');
    };
}