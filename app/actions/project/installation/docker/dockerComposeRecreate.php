<?php
/** @var \Project\Installation $inst */
return '<pre>' . html2text($inst->spf('docker/startContainers', true)) . '</pre>';


