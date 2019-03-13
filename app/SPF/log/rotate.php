<?php

#incspf error
#incspf log/checkWriteRights
#incspf rename

namespace SPF\log;

function rotate()
{

    checkWriteRights();
    $archiveDir = createArchiveDirectory();

    foreach (array('var/log', 'var/report') as $src) {

        $dst = $archiveDir . '/' . basename($src);

        umask(0);
        if (!mkdir($dst, 0775, true)) {
            \SPF\error("Can't create $dst folder");
        }

        if (!is_dir($src)) {
            continue;
        }

        $files = array_diff(scandir($src), array('.', '..'));
        foreach ($files as $file) {
            // don't move a symlink to directory, create directory and move files instead
            if (is_link($symlink = "$src/$file") && is_dir($symlink)) {
                umask(0);
                if (!mkdir("$dst/$file", 0775, true)) {
                    \SPF\error("Can't create $dst/$file folder");
                }
                foreach (array_diff(scandir($symlink), array('.', '..')) as $linkFile) {
                    \SPF\rename("$symlink/$linkFile", "$dst/$file/$linkFile");
                }
            } else {
                \SPF\rename("$src/$file", "$dst/$file");
            }
        }

    }
}

function createArchiveDirectory()
{

    $date = date('Y-m-d#H:i:s');

    // find unique name for new archive
    $i = 0;
    do {
        $suffix = $i === 0 ? '' : ".$i";
        $archiveDir = "var/archive/$date$suffix";
        $i++;
        if ($i > 100) {
            \SPF\error("Can't find unique name for archive folder");
        }
    } while (is_dir($archiveDir));

    umask(0);
    if (!mkdir($archiveDir, 0775, true)) {
        \SPF\error("Can't create $archiveDir folder");
    }

    return $archiveDir;

}
