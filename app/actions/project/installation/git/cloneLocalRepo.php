<?php

$localInst = $inst->getLocalInstallation();
// don't use $localInst->exec - it can't cd to appRoot when it's "src" e.g. State Of Art
if (!$localInst->fixRepo()) {
    throw new Exception(
        "Error for: " . cmd(
            'git clone %s %s',
            $localInst->project->repository->url,
            $localInst->folder
        )
    );
}
