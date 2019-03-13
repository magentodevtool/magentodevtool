<?php

$tables = $inst->spf('db/getTablesInfo', 'table_name');
return $inst->form('db/console/tables', compact('tables'));