<?php

$result = array(
    'success' => true,
    'message' => '',
);

try {
    $inst->log->rotate();
    $result['message'] = "Rotated";
} catch (Exception $e) {
    $result['success'] = false;
    $result['message'] = $e->getMessage();
}

return $inst->form('more/log/rotate/result', $result);
