<?php

$repoUrl = $localInst->exec('git config remote.origin.url');
if ($localInst->project->repository->url === $repoUrl) {
    return 1;
}

error('Repository URL in ' . $localInst->folder . '.git/config differs from value in projects.json, please fix');