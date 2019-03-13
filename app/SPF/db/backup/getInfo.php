<?php

# incspf error
# incspf exec

namespace SPF\db\backup;

function getInfo($dumpFile)
{
    if (!file_exists($dumpFile)) {
        error('File not found');
    }

    $fileExt = pathinfo($dumpFile, PATHINFO_EXTENSION);

    if ($fileExt == 'gz') {
        $tmpFile = $dumpFile . rand(1000, 99999999) . '.tmp';

        // exec was not used due to strange warning: gzip: stdout: Broken pipe
        $command = 'gunzip -c ' . $dumpFile . ' | dd bs=10485760 skip=0 count=1 >' . $tmpFile;
        $process = proc_open($command, array(2 => array("pipe", "w")), $pipes);
        if (is_resource($process)) {
            proc_close($process);
        }

        $fh = fopen($tmpFile, 'r');
    } else {
        $fh = fopen($dumpFile, 'r');
    }

    $infoStr = '';
    $infoFound = false;
    while (($line = fgets($fh)) !== false) {
        if (!$infoFound) {
            if (preg_match('~^/\* Devtool dump info:~', $line)) {
                $infoFound = true;
            } else {
                break;
            }
        } else {
            if (preg_match('~^End devtool dump info \*/~', $line)) {
                break;
            } else {
                $infoStr .= $line;
            }
        }
    }

    fclose($fh);
    if (isset($tmpFile)) {
        unlink($tmpFile);
    }

    return json_decode($infoStr);

}
