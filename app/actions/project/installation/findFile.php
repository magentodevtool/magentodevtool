<?php

foreach (array('file_name_rx', 'file_content_rx') as $param) {
    $rx = '~' . str_replace('~', "\\~", $ARG->$param) . '~';
    if (@preg_match($rx, '') === false) {
        error('Invalid rx for ' . $param);
    }
}

$files = $inst->spf('findFile',
    $ARG->file_name_rx,
    $ARG->file_name_rx_cond,
    $ARG->file_content_rx,
    $ARG->file_content_rx_cond
);

return $inst->form('findFile/result', compact('files'));
