<?php

$info = $inst->vars->get('dbImport/progressInfo/' . $dbImport->database);
if (!isset($info->insertedDataSize)) {
    return array('success' => true, 'html' => '');
}

$insertedDataPart = $info->insertedDataSize / $dbImport->dumpDataSize;
$queriesDonePart = $info->queriesDone / $dbImport->dumpInfo->queriesCount;
$averagePart = ($insertedDataPart + $queriesDonePart) / 2;
$percent = number_format($averagePart * 100, 2);

return [
    'success' => true,
    'html' => $inst->form(
        'db/import/progress',
        [
            'percent' => $percent,
            'currentTable' => $info->currentTable,
        ]
    )
];
