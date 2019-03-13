<?php

try {
    return $inst->spf('db/getTablesColumns');
} catch (Exception $e) {
    trigger_error('Autocomlete: tables and columns were not loaded: ' . $e->getMessage(), E_USER_WARNING);
}
