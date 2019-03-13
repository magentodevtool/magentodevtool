<?php
/** @var \Project\Installation $inst */
return '<pre>' . implode("\n", array_keys($inst->webServer->getConfig()->getServices())) . '</pre>';


