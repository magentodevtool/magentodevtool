<?php

namespace SPF;

function execCallback($command, $chunkCb, $usleep = 500)
{

    static $chunkSize = 8192; // standard

    $process = proc_open($command,
        array(
            array("pipe", "r"),
            array("pipe", "w"),
            array("pipe", "w")
        ),
        $pipes
    );
    stream_set_blocking($pipes[1], 0);

    $chunkLeft = '';
    $isNewLine = true;
    while (!feof($pipes[1])) {

        $chunk = fread($pipes[1], $chunkSize);
        if (!is_string($chunk)) {
            if ($usleep) {
                usleep($usleep);
            }
            continue;
        }
        $chunk = $chunkLeft . $chunk;
        $chunkLeft = '';

        while (($pos = strpos($chunk, "\n")) !== false) {
            $chunkChunk = substr($chunk, 0, $pos + 1);
            if (call_user_func($chunkCb, $chunkChunk, $isNewLine) === false) {
                proc_terminate($process);
                return;
            }
            $chunk = (string)substr($chunk, $pos + 1);
            $isNewLine = true;
        }

        if ($chunk !== '') {
            if (strlen($chunk) >= $chunkSize) {
                if (call_user_func($chunkCb, $chunk, $isNewLine) === false) {
                    proc_terminate($process);
                    return;
                }
                $isNewLine = false;
            } else {
                $chunkLeft = $chunk;
            }
        }

    }

    if ($chunkLeft !== '') {
        call_user_func($chunkCb, $chunk, $isNewLine);
    }

    if ($err = stream_get_contents($pipes[2])) {
        proc_close($process);
        error("execCallback error for \"$command\":\n$err");
    }

    proc_close($process);
}
