<?php

namespace Project\Installation\Generation;

class Composer
{
    /** @var \Project\Installation $inst */
    protected $inst;

    function __construct($inst)
    {
        $this->inst = $inst;
    }

    function run($noDev = false)
    {
        $addlArgs = '';
        if ($noDev) {
            $addlArgs .= ' --no-dev';
        }
        $cmd = 'composer install -o --ignore-platform-reqs' . $addlArgs;
        return (bool)$this->inst->exec($cmd);
    }

    function commit()
    {
        $this->inst->exec(
            array(
                // make sure no .git in modules otherwise git will not commit module files but folder only as a submodule
                "find vendor -type d -name '.git' -exec rm -rf \"{}\" +",
                // remove x on files otherwise it will case spam in git status on remote
                "find vendor -type f -print0 | xargs -0 chmod 644",
                // add only composed folders, changes outside of them are under developer control
                "git add -Af vendor lib setup bin >/dev/null",
            )
        );

        $diffToCommit = $this->inst->exec("git diff HEAD --cached | head");
        if (trim($diffToCommit) !== '') {
            $this->inst->exec("git commit -m 'composer install'");
            return true;
        }

        return false;
    }

    // return true if changes in composer.lock or any *.php file, changes in php files gives changes to composer classmap
    function areSourcesChanged($fromRev, $toRev)
    {
        try {
            $diff = $this->inst->exec("git diff %s..%s --name-status", $fromRev, $toRev);
        } catch (\Exception $e) {
            // if you recreate Alpha, Beta you will get "fatal: Invalid revision range..." so that just return true
            return true;
        }
        return preg_match('~(\.php$)|(composer\.lock$)|(app/patches)~m', $diff);
    }

    function isAvailable()
    {
        return
            $this->inst->project->type === 'magento2'
            && file_exists($this->inst->_appRoot . 'composer.lock');
    }

    function wasDoneInBranch($branch = null)
    {
        if (is_null($branch)) {
            $branch = $this->inst->git->getCurrentBranch();
        }

        return $this->inst->spf('git/fileExists', 'vendor/autoload.php', $branch);
    }

    function wasDone()
    {
        $assertFile = $this->inst->_appRoot . 'vendor/autoload.php';
        return file_exists($assertFile);
    }

}