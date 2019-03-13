<?php

if (!$inst->execOld('git reset --hard')) {
    error('Failed');
}