<?php

# incspf exec
# incspf error
# incspf listdir

namespace SPF\mage;

function fixMageRights($folder)
{
    if (!is_dir($folder)) {
        return;
    }

    if (!$apacheUserGroup = getApacheUserGroup()) {
        \SPF\error('Failed to determine web-server user:group');
    }

    // use getcwd because of __DIR__ is not what we need on local inst
    $docRoot = getcwd();

    // avoid symlinks affect
    $docRoot = realpath($docRoot) . '/';
    chdir($docRoot);

    $rwFiles = array();

    if ($folder == "media") {
        // exclude media/catalog
        $rwFiles = \SPF\listdir('media', true);
        $rwFiles = array_diff($rwFiles, array('media/catalog'));
    } else {
        $rwFiles[] = $folder;
    }

    $roFiles = array_merge(glob('var/exim/*/*/log'), glob('var/exim/*/*/archive'));

    $commands = array();

    foreach ($rwFiles as $rwFile) {
        $commands[] = 'sudo chown -R ' . $apacheUserGroup . ' ' . escapeshellarg($rwFile);
        $commands[] = 'sudo chmod -R a+rwX ' . escapeshellarg($rwFile);
    }

    foreach ($roFiles as $roFile) {
        $commands[] = 'sudo chmod -R o-w ' . escapeshellarg($roFile);
    }

    if ($folder == "media") {
        // fix media (not recursively)
        $commands[] = 'sudo chown ' . $apacheUserGroup . ' ' . escapeshellarg($folder);
        $commands[] = 'sudo chmod a+rwX ' . escapeshellarg($folder);
    }

    foreach ($commands as $command) {
        \SPF\exec($command);
    }
}

/**
 * FUNCTIONS
 */

function getApacheUserGroup()
{
    // for proserve hosting
    $configFile = '/etc/httpd/conf/httpd.conf';
    if (!file_exists($configFile)) {
        return 'www-data:www-data';
    }
    $config = file($configFile);
    $user = '';
    $group = '';
    foreach ($config as $line) {
        if (preg_match('~^\s*#~ism', $line)) {
            continue;
        }
        if (preg_match('~^\s*User\s+([^\s]+)\s*$~ism', $line, $matches)) {
            $user = $matches[1];
        }
        if (preg_match('~^\s*Group\s+([^\s]+)\s*$~ism', $line, $matches)) {
            $group = $matches[1];
        }
    }
    if (!empty($user) && !empty($group)) {
        return "$user:$group";
    }
}
