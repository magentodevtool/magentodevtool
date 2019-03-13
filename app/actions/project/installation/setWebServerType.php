<?php

$serverType = $ARG->serverType;

$inst->setWebServerType($serverType);
$inst->setLastWebServerType($serverType);

return true;
