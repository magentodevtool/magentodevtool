<?php

$result = array(
    'success' => true,
    'message' => '',
);

try {
    $inst->generation->scss->run();
    $result['message'] = "Done";
} catch (Exception $e) {
    $result['success'] = false;
    $result['message'] = $e->getMessage();
}

return $inst->form('scss/compile/result', $result);