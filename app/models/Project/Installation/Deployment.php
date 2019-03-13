<?php

namespace Project\Installation;

class Deployment
{
    /** @var \Project\Installation $inst */
    protected $inst;
    protected $dwdBranchRegexp = '~^\s*([0-9A-Z]+\s*-\s*\d+)~';
    protected $dwdCommitRegexp = '~([0-9A-Z]+\s*-\s*\d+)~';

    /** @var Deployment\customNotes $customNotes */
    public $customNotes;
    /** @var Deployment\Lock */
    public $lock;
    /** @var Deployment\LocalInstallation */
    public $localInstallation;

    public function __construct($inst)
    {
        $this->inst = $inst;
        $this->customNotes = new Deployment\customNotes($this->inst);
        $this->lock = new Deployment\Lock($this->inst);
        $this->localInstallation = new Deployment\LocalInstallation($this->inst);
    }

    function getType()
    {

        $currentRemoteBranch = $this->inst->git->getCurrentBranch();

        if ($currentRemoteBranch && $this->isBranchStaging($currentRemoteBranch)) {
            return 'staging';
        } else {
            if ($this->inst->isCloud) {
                if ($currentRemoteBranch === 'production') {
                    return 'production';
                }
            } else {
                $currentRemoteTags = $this->inst->git->getCurrentTags();
                if (!$currentRemoteBranch && is_array($currentRemoteTags) && count($currentRemoteTags)) {
                    return 'production';
                }
            }
        }

        return false;

    }

    /**
     * @param \Project\Installation $localInst
     * @return object
     */
    function getData()
    {

        $inst = $this->inst;

        // fast return if failed to determine deployment strategy
        if (!$type = $this->getType()) {
            return (object)array('type' => false);
        }

        try {
            $customNotesHash = $this->customNotes->getHash();
        } catch (\Exception $e) {
            // don't handle error here, it will be handled in customNotes.phtml
            $customNotesHash = null;
        }

        $mageInfo = $inst->magento->getInfo(true);
        if ($mageInfo->version === 'Undefined') {
            \error('Can\'t determine Magento version');
        }

        // add data which are required for any deployment type
        $deployment = array(
            'type' => $type,
            'branchesToSelect' => $this->getBranchesToSelect(),
            'currentRemoteBranch' => $type === 'production' ? 'master' : $inst->git->getCurrentBranch(),
            'customNotesHash' => $customNotesHash,
            'mageVersion' => $mageInfo->version,
            'mage2' => (object)[
                'mode' => $inst->spf('mage/getM2Mode'),
                'locales' => $inst->spf('deployment/getLocales'),
                'cloud' => isset($inst->cloud) ? $inst->cloud : null,
            ],
        );

        if ($type !== 'production') {
            return (object)$deployment;
        }

        // add data which are required for production deployment only

        $highestTagName = '0';
        $tags = $this->localInstallation->get()->git->getTags();
        foreach ($tags as $name => $tag) {
            if (!$tag['local']) {
                $highestTagName = $name;
                break;
            }
        }

        if ($inst->isCloud) {
            $currentRemoteTags = [];
        } else {
            $currentRemoteTags = $inst->git->getCurrentTags();
        }

        $deployment = array_merge(
            $deployment,
            array(
                'currentRemoteTags' => $currentRemoteTags,
                'highestTagName' => $highestTagName,
                'newTagName' => incrementVersion($highestTagName),
                'isCurrentTagHighest' => in_array($highestTagName, $currentRemoteTags),
            )
        );

        return (object)$deployment;

    }

    function getBranchesToSelect()
    {
        $branches = new \stdClass();
        foreach ($this->localInstallation->get()->git->getRemoteBranches() as $key => $branch) {
            if (!($this->inst->git->isDevelopmentBranch($branch['name']) || $branch['name'] === 'master')) {
                continue;
            }
            $branches->{$key} = (object)array(
                'name' => $branch['name'],
            );
        }
        return $branches;
    }

    function isBranchStaging($branch)
    {
        if ($this->inst->isCloud) {
            return preg_match('~^(integration|staging)~', $branch);
        } else {
            return preg_match('~^(Alpha|Beta)~', $branch);
        }
    }

    public function getDeploymentNotes($deployment)
    {
        return array_merge(
            $this->getDwdForeignCommitsNotes($deployment)
        );
    }

    public function getDwdForeignCommitsNotes($deployment)
    {

        $notes = array();
        foreach ($deployment->branchesToDeploy as $branch) {

            if (!$this->isDwdBranch($branch)) {
                continue;
            }

            $commits = $this->getDwdForeignCommits($branch);
            if (count($commits)) {
                $notes[] = $this->inst->form('deployment/notes/foreignCommitsInDwd', compact('branch', 'commits'));
            }

        }

        return $notes;

    }

    public function isDwdBranch($branch)
    {

        if (!preg_match($this->dwdBranchRegexp, $branch)) {
            return false;
        }

        return true;

    }

    public function getDwdForeignCommits($branch)
    {

        if (!preg_match($this->dwdBranchRegexp, $branch, $match)) {
            return false;
        }

        $issueCode = $match[1];

        $commitsLog = $this->localInstallation->get()->git->getBranchesDiffLog('master', $branch);
        $commits = array();
        foreach ($commitsLog as $commit) {
            if (!preg_match($this->dwdCommitRegexp, $commit['comment'], $match)) {
                $commits[] = $commit['comment'];
            } elseif ($match[1] != $issueCode) {
                $commits[] = $match[1];
            }
        }

        return $commits;

    }

    function getDepins()
    {
        return $this->inst->spf('depins/getList');
    }

    function getDepinName($file, $type = 'db')
    {
        return $this->inst->spf('depins/getName', $file, $type);
    }

    function getBuildInst()
    {
        $inst = $this->inst;
        if ($inst->hasSshAccess) {
            return $inst;
        }
        $buildInstName = $this->getBuildInstName();
        return \Projects::getInstallation($inst->source, $inst->project->name, $buildInstName);
    }

    function getBuildInstName()
    {
        return $this->inst->name . " (build)";
    }

}
