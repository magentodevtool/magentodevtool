<?php
/** @var \Project\Installation $inst */
/** @var \stdClass $ARG */

$inst->vars->set('lastPhpCode', $ARG->consoleText);

try {
    if (!empty($ARG->dockerService)) {
        $output = $inst->execInDockerService($ARG->dockerService, $ARG->dockerUser, 'php -r %s', $ARG->consoleText);
    } else {
        $output = $inst->exec('php -r %s', $ARG->consoleText);
    }
} catch (Exception\Bash $e) {
    $output = $e->getMessage();
}

return html2text($output);
