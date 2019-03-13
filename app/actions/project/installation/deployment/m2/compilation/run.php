<?php

/** @var \Project\Installation $localInst */

$toDo = getToDoList($localInst);
$freeCores = cpuCoresCount();
$running = [];

if (!$freeCores) {
    error('Failed to get CPU cores count');
}

$status = [];
foreach ($toDo as $key => $command) {
    $status[$key] = '';
}

$outputDir = 'var/deployment/compilation/';
$outputDirAbs = $localInst->folder . $outputDir;
if (!is_dir($outputDirAbs)) {
    mkdir($outputDirAbs, 0777, true);
}

try {

    while (true) {

        foreach ($running as $pid => $commandInfo) {
            if (isPidRunning($pid)) {
                continue;
            }
            unset($running[$pid]);
            $freeCores++;

            $outputFileAbs = $outputDirAbs . $commandInfo['key'];
            $output = file_get_contents($outputFileAbs);
            preg_match('~exitCode=([0-9]+)$~', $output, $matches);
            if (!isset($matches[1])) {
                $status[$commandInfo['key']] = 'error';
                error('Exit code not found');
            }
            $exitCode = (int)$matches[1];
            if ($exitCode !== 0) {
                $status[$commandInfo['key']] = 'error';
                error('"' . $commandInfo['command'] . "\" failed, see $outputFileAbs");
            }
            $status[$commandInfo['key']] = 'done';
        }

        if (!count($toDo) && !count($running)) {
            break;
        }

        while ($freeCores && count($toDo)) {
            $command = reset($toDo);
            $commandKey = key($toDo);
            $outputFile = $outputDir . $commandKey;

            $cmd =
                shellescapef("cd %s 2>&1", $localInst->_appRoot)
                . shellescapef(
                    " && nohup sh -c %s >%s 2>&1",
                    "$command; echo \"exitCode=$?\"", $outputFile
                )
                . " & echo $!";

            $o = $r = null;
            // delay for 0.25 sec, it's possible fix for "There are no commands defined in the "setup:static-content" namespace."
            usleep(250000);
            exec($cmd, $o, $r);
            $pid = implode('\n', $o);
            if ($r !== 0) {
                error('Error occurred when compilation run');
            }
            $running[$pid] = ['key' => $commandKey, 'command' => $command];
            $status[$commandKey] = 'running';
            unset($toDo[$commandKey]);
            $freeCores--;
        }

        $localInst->vars->set('deployment/m2/compilation/status', $status);

        sleep(2);
    }

} catch (\Exception $exception) {
}

if (isset($exception)) {
    // kill all running processes, use pkill which can get only one pid as arg, no list
    foreach (array_keys($running) as $pid) {
        exec('pkill -TERM -P ' . $pid);
    }
    foreach ($running as $pid => $commandInfo) {
        $status[$commandInfo['key']] = 'killed';
    }
}

$localInst->vars->set('deployment/m2/compilation/status', $status);

if (isset($exception)) {
    deploymentDialog('m2/compilation/fail', compact('exception'));
} else {
    deploymentDialog('m2/compilation/success');
}
