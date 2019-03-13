<?php

if ($hash = $inst->deployment->lock->capture()) {
    deploymentDialog('lock/capture/success', compact('hash'));
} else {
    deploymentDialog('lock/capture/failed', $inst->deployment->lock->getInfo());
}
