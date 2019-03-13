<?php

$result = $inst->config->backup($ARG->comment);

return $inst->form('config/backup/result', $result);
