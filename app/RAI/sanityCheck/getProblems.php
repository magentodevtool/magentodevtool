<?php

require_once __DIR__ . '/../init.php';
require_once 'inc/SanityCheck.php';
require_once 'inc/SanityCheckMageHelper.php';
require_once MAGE_ROOT . 'app/Mage.php';
Mage::app();

$problems = SanityCheck::renderAllConflicts();

die(json_encode($problems));
