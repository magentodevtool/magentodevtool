<?php

$remoteInst = \Projects::getInstallation($inst->source, $inst->project->name, $ARG->remote);

$sizeInfo = $remoteInst->spf('getDirSizeInfo', $ARG->rootDir . '/' . $ARG->folder, false, $ARG->excludeRegexp);

return humanSizeFormat($sizeInfo['bytes']);
