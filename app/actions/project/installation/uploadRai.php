<?php

if (!$rai = $inst->uploadRai(false)) {
    error('Failed to upload RAI');
}

// rm rai with delay
$inst->execOld('nohup php %s 45 >/dev/null 2>&1 &', $rai->dir . 'remove.php');

return array(
    'url' => $rai->url,
    'PWD' => $rai->PWD,
);
