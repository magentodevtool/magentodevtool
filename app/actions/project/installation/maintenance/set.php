<?php
$allowedIPs = preg_replace('~\s+~sm', '', $ARG);
$allowedIPs = trim(preg_replace('~,+~sm', ',', $allowedIPs), ',');
$inst->vars->set('maintenanceIPs', $ARG);

$inst->maintenance->turnOn($allowedIPs);
