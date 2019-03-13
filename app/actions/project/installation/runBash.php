<?php
/** @var \Project\Installation $inst */
/** @var \stdClass $ARG */

$inst->vars->set('lastBashCommands', $ARG->consoleText);

try {
    if (!empty($ARG->dockerService)) {
        $output = $inst->execInDockerService($ARG->dockerService, $ARG->dockerUser, 'sh -c %s', $ARG->consoleText);
    } else {
        $output = $inst->exec('eval %s', $ARG->consoleText);
    }
} catch (Exception\Bash $e) {
    $output = $e->getMessage();
}

return html2text($output);
