<?php

namespace Project\Installation\Generation;

class Scss
{
    /** @var \Project\Installation $inst */
    protected $inst;
    protected $themes;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    function run()
    {
        global $inst;

        $themes = $this->getThemes();

        $environment = preg_match('~beta|live~i', $inst->name) ? 'production' : 'development';

        foreach ($themes as $theme) {
            $this->inst->exec(
                array(
                    "cd %s",
                    "compass compile -e %s --force --boring scss",
                ), $theme, $environment
            );
        }
    }

    function commit()
    {
        $themes = $this->getThemes();
        if (!count($themes)) {
            return;
        }
        foreach ($themes as $theme) {
            $this->inst->exec(
                array(
                    "cd %s",
                    "git add -f -A css",
                ), $theme
            );
        }
        $diffToCommit = $this->inst->exec("git diff HEAD --cached | head");
        if (trim($diffToCommit) !== '') {
            $this->inst->exec("git commit -m 'compass compile'");
            return true;
        }
    }

    function rmCache()
    {
        foreach ($this->getThemes() as $theme) {
            $this->inst->exec(
                array(
                    "cd %s",
                    "rm -rf .sass-cache scss/.sass-cache",
                ),
                $theme
            );
        }
    }

    function getThemes()
    {
        if (is_array($this->themes)) {
            return $this->themes;
        }
        $this->themes = $this->inst->spf('generation/scss/getThemes');
        return $this->themes;
    }

    public function areSourcesChanged($fromRev, $toRev)
    {
        return (bool)$this->inst->exec("git diff %s..%s --name-status -- '*.scss'", $fromRev, $toRev);
    }

    public function runOnSourcesChange($fromRev, $toRev)
    {
        if ($this->inst->type === 'remote') {
            return;
        }
        if (!$this->areSourcesChanged($fromRev, $toRev)) {
            return;
        }
        $this->run();
    }

    function isAvailable()
    {
        return $this->inst->project->type === 'magento1';
    }

}