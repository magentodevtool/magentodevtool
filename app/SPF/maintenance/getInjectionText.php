<?php

#incspf error

namespace SPF\maintenance;

function getInjectionText($allowedIPs)
{
    $allowedIPsExpr = var_export(explode(",", $allowedIPs), true);
    $content = <<<content
<?php
if(in_array(\$_SERVER['REMOTE_ADDR'], $allowedIPsExpr)) {
   return;
}
include('errors/503.php');
exit;
content;

    return $content;
}