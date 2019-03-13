<?php

require_once '../init.php';

if ($instInfo->project->type == 'magento2') {
    require_once './bo/m2.php';
} else {
    require_once './bo/m1.php';
}