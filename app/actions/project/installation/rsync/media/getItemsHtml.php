<?php

$remoteInst = Projects::getInstallation($inst->source, $inst->project->name, $ARG);
$mediaFolder = $remoteInst->_docRoot . 'media/';
$items = $remoteInst->spf('rsync/media/getItems');

return $inst->form('rsync/media/items', compact('items', 'remoteInst'));
