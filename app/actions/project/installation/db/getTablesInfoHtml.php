<?php
$tables = $inst->spf('db/getTablesInfo');
return $inst->form('db/backup/tablesInfo', compact('tables'));
