<?php

foreach (array('table_name_rx', 'field_name_rx', 'field_value_rx') as $param) {
    $rx = '~' . str_replace('~', "\\~", $ARG->$param) . '~';
    if (@preg_match($rx, '') === false) {
        error('Invalid rx for ' . $param);
    }
}

$params = $inst->spf('db/search',
    $ARG->table_name_rx,
    $ARG->table_name_rx_cond,
    $ARG->field_name_rx,
    $ARG->field_name_rx_cond,
    $ARG->field_value_rx,
    $ARG->field_value_rx_cond
);
$params = array_merge($params, (array)$ARG);

return $inst->form('db/search/result', $params);
