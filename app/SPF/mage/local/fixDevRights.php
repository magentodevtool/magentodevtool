<?php

# incspf exec
# incspf error

namespace SPF\mage\local;

function fixDevRights()
{

    $user = trim(`whoami`);

    if (empty($user)) {
        \SPF\error('Username wasn\'t provided');
    }

    // use getcwd because of __DIR__ is not what we need on local inst
    $docRoot = getcwd();

    // avoid symlinks affect
    $docRoot = realpath($docRoot) . '/';
    chdir($docRoot);

    $filesToFix = scandir('.');
    $filesToFix = array_diff($filesToFix, array('.docker', 'var', 'media', '.', '..'));
    if (!is_dir('.git') && is_dir('../.git')) {
        $filesToFix[] = '../.git';
    }

    $commands = array();
    if (count($filesToFix)) {
        $filesArg = implode(' ', array_map('escapeshellarg', $filesToFix));
        $commands[] = 'sudo chown -R ' . escapeshellarg($user) . ' ' . $filesArg;
        $commands[] = 'sudo chmod -R u+rwX,o+rX ' . $filesArg;
    }

    // fix docRoot without -R
    $commands[] = 'sudo chown ' . escapeshellarg($user) . ' .';
    $commands[] = 'sudo chmod u+rwX,o+rX .';

    foreach ($commands as $command) {
        \SPF\exec($command);
    }

}
