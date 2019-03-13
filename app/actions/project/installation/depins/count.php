<?php

try {
    return $inst->spf('depins/count');
} catch (Exception $e) {
    return 0;
}
