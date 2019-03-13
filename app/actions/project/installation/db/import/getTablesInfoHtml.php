<?php

return array(
    'success' => true,
    'html' => $inst->form('db/import/tablesInfo', array('tablesInfo' => $dbImport->dumpInfo->tables))
);
