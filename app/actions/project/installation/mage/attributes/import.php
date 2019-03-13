<?php

$result = $inst->execRaiScriptByUrl('attributes/import.php');

if ($result === false) {
    error('Fail to run import. Check if domain is available and there is no HTTP authentication for Devtool');
}

return $inst->form('attributes/import/result', $result);
