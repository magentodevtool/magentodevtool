<?php

$remoteInst = \Projects::getInstallation($inst->source, $inst->project->name, $ARG->remote);
return $inst->rsync->run($remoteInst, $ARG->srcFolders, $ARG->destFolder);
