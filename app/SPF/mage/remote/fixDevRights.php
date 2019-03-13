<?php

# incspf exec
# incspf error

namespace SPF\mage\remote;

function fixDevRights()
{

    // use getcwd because of __DIR__ is not what we need on local inst
    $docRoot = getcwd();

    // avoid symlinks affect
    $docRoot = realpath($docRoot) . '/';
    chdir($docRoot);

    if (!$developerGroup = getDeveloperGroup()) {
        \SPF\error('Failed to determine developer group');
    }

    $filesToFix = scandir('.');
    $filesToFix = array_diff($filesToFix, array('var', 'media', '.', '..'));
    if (!is_dir('.git') && is_dir('../.git')) {
        $filesToFix[] = '../.git';
    }

    $commands = array();
    foreach ($filesToFix as $file) {
        $commands[] = 'sudo chgrp -R ' . escapeshellarg($developerGroup) . ' ' . escapeshellarg($file);
        $commands[] = 'sudo chmod -R g+rwX,o+rX ' . escapeshellarg($file);
    }

    // fix docRoot without -R
    $commands[] = 'sudo chgrp ' . escapeshellarg($developerGroup) . ' .';
    $commands[] = 'sudo chmod g+rwX,o+rX .';

    foreach ($commands as $command) {
        \SPF\exec($command);
    }

}

function getDeveloperGroup()
{
    // developer group must be the first group of the current user
    $user = trim(`whoami`);
    $groupsOut = trim(`groups $user`);
    $groupsStr = preg_replace('~.+ : ~', '', $groupsOut);
    $groups = explode(' ', $groupsStr);
    if (!count($groups)) {
        return '';
    }
    return trim($groups[0]);
}
