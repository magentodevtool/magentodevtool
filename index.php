<?php

list($startMicroTime, $startTime) = explode(' ', microtime());
define('START_TIME', $startTime);
define('START_MICROTIME', $startMicroTime);

require_once 'app/init.php';

dispatchAction();

renderPage();
