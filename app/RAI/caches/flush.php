<?php

require_once '../init.php';

if ($instInfo->project->type == 'magento2') {
    require_once './flush/m2.php';
} else {
    require_once './flush/m1.php';
}