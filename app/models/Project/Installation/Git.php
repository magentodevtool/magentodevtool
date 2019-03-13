<?php

namespace Project\Installation;

class Git
{

    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    /**
     * @deprecated
     */
    protected $fetchResult;

    /**
     * @deprecated
     */
    public $fetchOutput;

    protected $wasFetchDone;
    protected $fetchException;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    /**
     * @deprecated use fetch
     */
    function fetchOld()
    {
        if (is_null($this->fetchResult)) {
            $this->fetchResult = $this->inst->execOld('git fetch');
            $this->fetchOutput = $this->inst->execOutput;
        }
        return $this->fetchResult;
    }

    function fetch()
    {
        if ($this->wasFetchDone) {
            if ($this->fetchException) {
                throw $this->fetchException;
            } else {
                return;
            }
        }

        try {
            $this->inst->execTimeout('git fetch --prune', [], 180);
        } catch (\Exception $e) {
            $this->fetchException = $e;
        }

        $this->wasFetchDone = true;

        if ($this->fetchException) {
            throw $this->fetchException;
        }
    }

    function getRemoteBranches($alphaName = 'Alpha', $betaName = 'Beta')
    {

        try {
            $this->fetch();
        } catch (\Exception $e) {
        }

        $branches = $this->inst->spf('git/getRemoteBranches', $alphaName, $betaName);

        uasort($branches, function ($a, $b) {
            $a = $a['name'];
            $b = $b['name'];
            if (!preg_match('~0-9~', $a . $b)) {
                return strcmp($a, $b);
            }
            return version_compare($a, $b);
        });

        // adjust branches order
        $sortedBranches = array();
        $order = [
            '~^master$~',
            '~^Live~',
            '~^Beta~',
            '~^Alpha~',
            '~^production~',
            '~^staging~',
            '~^integration~',
            '~.+~'
        ];
        foreach ($order as $branchRx) {
            foreach ($branches as $branch => $branchInfo) {
                if (preg_match($branchRx, $branch)) {
                    $sortedBranches[$branch] = $branchInfo;
                }
            }
        }

        return $sortedBranches;

    }

    // only remote branches
    function getBranchesDiffLog($branch1, $branch2, $allRemote = true, $excludeMerges = true)
    {
        return $this->inst->spf('git/getBranchesDiffLog', $branch1, $branch2, $allRemote, $excludeMerges);
    }

    function getTags()
    {

        $closestInst = \Project::getClosestInstallation($this->inst);

        $closestInst->git->fetchOld();
        $remoteTags = $closestInst->git->getRemoteTags();

        $this->fetchOld();
        if ($this->inst->execOld('git show-ref --tags -d')) {

            $tags = array();
            foreach (explode("\n", $this->inst->execOutput) as $tagInfo) {
                if (!preg_match('~^.+ refs/tags/([^^]+)(.*)$~', $tagInfo, $ms)) {
                    continue;
                }
                $name = $ms[1];
                $isAnnotated = !empty($ms[2]);
                $tag = array('name' => $name, 'local' => true);
                if ($isAnnotated) {
                    $tagInst = isset($remoteTags[$tag['name']]) ? $closestInst : $this->inst;
                    $tagInst->execOld('git cat-file tag %s', $name);
                    if (preg_match('~tagger (.+) <.+> ([0-9]+) \+[0-9]+\n\n(.+)$~ism', $tagInst->execOutput, $ms)) {
                        $tag['tagger'] = preg_replace('~<.+>~', '', $ms[1]);
                        $tag['date'] = date('Y-m-d H:i:s', $ms[2]);
                        $tag['comment'] = $ms[3];
                        $authorRx = '~^Author: (.+)$~m';
                        if (preg_match($authorRx, $tag['comment'], $ms)) {
                            $tag['comment'] = trim(preg_replace($authorRx, '', $tag['comment']));
                            $tag['tagger'] = $ms[1];
                        }
                    }
                }
                $tags[$name] = $tag;
            }

            foreach ($remoteTags as $remoteTag) {
                if (isset($tags[$remoteTag])) {
                    $tags[$remoteTag]['local'] = false;
                }
            }

            uasort($tags, function ($a, $b) {
                $a = version_extract($a['name']);
                $b = version_extract($b['name']);
                return version_compare($b, $a);
            });

            return $tags;

        }

        return array();

    }

    function getRemoteTags()
    {
        $execOutput = $this->inst->exec('git ls-remote --tags origin');
        $remoteTags = array();
        foreach (explode("\n", $execOutput) as $remoteTag) {
            if (preg_match('~\srefs/tags/([^^]+)$~', $remoteTag, $ms)) {
                $remoteTags[$ms[1]] = $ms[1];
            }
        }
        return $remoteTags;
    }

    function getCherryPickCommits($currentBranch, $branches = array())
    {
        if (empty($branches)) {
            $branches = $this->getRemoteBranches();
        }

        $branchesCommits = array();
        foreach ($branches as $branch) {
            if (!$this->isDevelopmentBranch($branch['name'])) {
                continue;
            }
            $commitsToSelect = $this->getBranchesDiffLog($currentBranch, $branch['name']);
            if (!count($commitsToSelect)) {
                continue;
            }
            $branchesCommits[$branch['name']] = $this->getBranchesDiffLog($currentBranch, $branch['name']);
        }

        return $branchesCommits;
    }

    function getBranchBehindCommits($branch)
    {
        return $this->getBranchesDiffLog($branch, 'origin/' . $branch, false, false);
    }

    function getBranchAheadCommits($branch)
    {
        return $this->getBranchesDiffLog('origin/' . $branch, $branch, false, false);
    }

    function getCurrentBranch()
    {

        if ($this->inst->isCloud) {
            return $this->inst->cloud->branch;
        }

        if (!$this->inst->execOld('git branch')) {
            return false;
        }

        foreach (explode("\n", $this->inst->execOutput) as $line) {
            if (preg_match('~^\* ([^(].*)$~', $line, $ms)) {
                $currentBranch = $ms[1];
                break;
            }
        }

        return !isset($currentBranch) ? false : $currentBranch;

    }

    function getCurrentTag()
    {

        if ($this->getCurrentBranch() !== false) {
            return false;
        }

        if (!$this->inst->execOld(array('git reflog | grep " checkout: " --max-count 1'))) {
            return false;
        }

        $output = trim($this->inst->execOutput);

        if (preg_match("~ to (.+)$~", $output, $ms) && isset($ms[1])) {
            return $ms[1] == $this->getCurrentHashOld() ? false : $ms[1];
        } else {
            $tags = $this->getCurrentTags();
            return !$tags ? false : current($tags);
        }

    }

    /**
     * @deprecated
     */
    function getCurrentHashOld()
    {
        if (!$this->inst->execOld('git rev-parse HEAD')) {
            return false;
        }
        return trim($this->inst->execOutput);
    }

    function getCurrentHash()
    {
        return trim($this->inst->exec('git rev-parse HEAD'));
    }

    function getBranchHash($branch)
    {
        return trim($this->inst->exec('git rev-parse %s', "origin/$branch"));
    }

    function getCurrentTags()
    {

        if ($this->getCurrentBranch() !== false) {
            return array();
        }

        if (!$this->inst->execOld(array('git show-ref --tags -d', 'git rev-parse HEAD'))) {
            return false;
        }

        $output = explode("\n", trim($this->inst->execOutput));
        $currentHash = end($output);
        array_pop($output);

        $tagsHash = array();
        foreach ($output as $line) {
            if (preg_match("~^([a-z0-9]+) refs/tags/(.+?)(\\^\\{\\})?$~", $line, $ms)) {
                $tagsHash[$ms[2]] = $ms[1];
            }
        }

        $tags = array();
        foreach ($tagsHash as $tagName => $tagHash) {
            if ($tagHash === $currentHash) {
                $tags[$tagName] = $tagName;
            }
        }

        return $tags;

    }

    function getModificationsNotices($diff)
    {
        $coreFilesLimit = 10;
        $notices = array();
        $coreModifications = array();
        $junkModifications = array();
        $incompatibleNamespaces = array();
        $isMagento1 = $this->inst->project->type === 'magento1';

        foreach ($diff->notices as $notice) {
            $notices[] = array('message' => $notice,);
        }

        if ($this->inst->type === 'remote') {
            return $notices;
        }

        foreach ($diff->diff as $key => $row) {

            if (
                $isMagento1 &&
                in_array($row->type, array('A', 'M')) &&
                preg_match('~app/code/.*/etc/config\.xml$~', $row->file)
            ) {
                $incompatibleNamespaces = array_merge(
                    $incompatibleNamespaces,
                    $this->inst->magento->getIncompatibleModuleNamespaces($row->file)
                );
            }

            if ($row->type !== 'M') {
                continue;
            }

            if (
                preg_match('~.+.(php|phtml)$~', $row->file)
                && preg_match_all(
                    '~^\+(.*[^\w])?(die|exit|print_r|var_dump|var_export)([^\w]|$)~im',
                    $row->diff,
                    $matches
                )
            ) {
                $junkModifications[] = "{$row->file} - " . implode(', ', $matches[2]) . "";
            }

            if ($isMagento1 && preg_match('~app/code/core|app/Mage\.php~', $row->file)) {
                if (count($coreModifications) < $coreFilesLimit) {
                    $coreModifications[] = $row->file;
                } elseif (count($coreModifications) == $coreFilesLimit) {
                    $coreModifications[] = '...';
                }
            }
        }

        if ($coreModifications) {
            $notices[] = array(
                'message' => "Detected changes in Magento Core:",
                'details' => $coreModifications
            );
        }

        if ($junkModifications) {
            $notices[] = array(
                'message' => "Possible junk in changes:",
                'details' => $junkModifications
            );
        }

        if ($incompatibleNamespaces) {
            $notices[] = array(
                'message' => "Possible conflict of config.xml with other modules (it's recommended to use module name in namespaces):",
                'details' => $incompatibleNamespaces
            );
        }

        return $notices;
    }

    function createTag($name, $comment, $parent = 'origin/master')
    {
        $commentExp = shellescapef('-m %s', $comment);

        if (\Config::getNode('isCentralized')) {
            $commentExp .= shellescapef(' -m %s', 'Author: ' . LDAP_USER);
        }

        $this->inst->exec(
            array(
                'git fetch',
                'git tag %s %s -a ' . $commentExp,
                'git push origin %s'
            ),
            $name, $parent, $name
        );
    }

    function getLocalBranchesToPrune()
    {

        $this->inst->execOld(array('git fetch', 'git remote prune origin'));

        if (!$this->inst->execOld('git branch -vv')) {
            return false;
        }
        $branches = explode("\n", $this->inst->execOutput);
        $trackedBranches = array();
        foreach ($branches as $branch) {
            if (preg_match('~^\\*?\\s+([^\\s]+).+\\[origin/([^\\s]+)[\\]\\:]~', $branch, $ms)) {
                // local branch => remote branch
                $trackedBranches[$ms[1]] = $ms[2];
            }
        }

        $remoteBranches = $this->getRemoteBranches();

        $branchesToPrune = array();
        foreach ($trackedBranches as $localBranch => $trackedBranch) {
            if (!isset($remoteBranches[$trackedBranch])) {
                $branchesToPrune[] = $localBranch;
            }
        }

        return $branchesToPrune;

    }

    function getRemoteBranchesToPrune()
    {
        if (!$this->fetchOld()) {
            return false;
        }
        $branchesToPrune = array();
        foreach ($this->getRemoteBranches() as $remoteBranch) {
            if (!$this->isDevelopmentBranch($remoteBranch['name'])) {
                continue;
            }
            if (!is_array($commits = $this->getBranchesDiffLog('master', $remoteBranch['name']))) {
                continue;
            }
            if (!count($commits)) {
                $branchesToPrune[] = $remoteBranch['name'];
            }
        }
        return $branchesToPrune;
    }

    function getTagsToPrune()
    {
        $tags = $this->getTags();
        $tagsToPrune = array();
        foreach ($tags as $tag) {
            if ($tag['local']) {
                $tagsToPrune[] = $tag;
            }
        }
        return $tagsToPrune;
    }

    function getModifications($options = array())
    {
        return (object)array(
            'diff' => $this->getDiff('HEAD', $options),
            'currentBranch' => $this->getCurrentBranch(),
        );
    }

    function getCleanBranchesDiff($branch1, $branch2, $options = array())
    {
        // clean mean "..." which shows only what was done in branch2 (excluding what was done in branch1)
        $filter = shellescapef("%s...%s", "origin/$branch1", "origin/$branch2");
        return $this->getDiff($filter, $options);
    }

    function getDiff($filter, $options = array(), $withMeta = false)
    {
        return $this->inst->spf('git/getDiff', $filter, $options, $withMeta);
    }

    function getRevsDiff($fromRev, $toRev)
    {
        if (!$this->inst->execOld('git diff %s..%s --name-status', $fromRev, $toRev)) {
            return false;
        }
        if (trim($this->inst->execOutput) === '') {
            return array();
        }
        $changes = array_map('trim', explode("\n", $this->inst->execOutput));
        foreach ($changes as $key => $line) {
            $tmpArray = explode("\t", $line);
            $changes[$key] = array('type' => $tmpArray[0], 'file' => $tmpArray[1]);
        }
        return $changes;
    }

    function getCleanRevsDiff($fromRev, $toRev, $limitLines = null)
    {
        $cmd = 'git diff %s...%s --name-status';
        if ($limitLines) {
            $cmd .= ' | head -n ' . shellescapef('%s', $limitLines);
        }
        $output = $this->inst->exec($cmd, $fromRev, $toRev);
        if (trim($output) === '') {
            return array();
        }
        $changes = array_map('trim', explode("\n", $output));
        foreach ($changes as $key => $line) {
            $tmpArray = explode("\t", $line);
            $changes[$key] = array('type' => $tmpArray[0], 'file' => $tmpArray[1]);
        }
        return $changes;
    }

    function getFileLastCommit($file)
    {
        return $this->inst->spf('git/getFileLastCommit', $file);
    }

    public function getChangeListHtml($headHash = null)
    {

        $format = $this->getChangeListFormat('%h -%d %s (%cr) &lt;%an&gt;');
        $this->inst->execOld(
            array(
                'git fetch --tags -q',
                "git log " . ($headHash ? "origin/master..{$headHash}" : '') . " --max-count=50 --decorate --graph --pretty=%s"
            ),
            $format
        );

        return $this->inst->execOutput;
    }

    public function getChangeListFormat($content)
    {
        $classes = array(
            '%h' => 'hash',
            '%d' => 'references-names',
            '%s' => 'subject',
            '%cr' => 'relative-date',
            '%an' => 'author-name',
        );
        foreach ($classes as $parameter => $class) {
            $html = "<span class=\"{$class}\">$parameter</span>";
            $content = str_replace($parameter, $html, $content);
        }

        return "format:" . $content;
    }

    public function checkout($refName)
    {
        $output = $this->inst->exec('git checkout %s', $refName);
        return $this->getOutputWarnings($output);
    }

    public function hardReset($refName)
    {
        $output = $this->inst->exec('git reset --hard %s', $refName);
        return $this->getOutputWarnings($output);
    }

    public function getOutputWarnings($output)
    {
        $warnings = array();
        if (preg_match_all('~(fatal|warning|error):.*~i', $output, $matches)) {
            $warnings = $matches[0];
        }
        return $warnings;
    }

    public function isDevelopmentBranch($branchName)
    {
        return !(bool)preg_match('~^(Alpha|Beta|Live|master|integration|staging|production)~', $branchName);
    }

    public function isUnfinishedMerge()
    {
        return $this->inst->spf('git/isUnfinishedMerge');
    }

    function getRepoCloneTime()
    {
        return filemtime($this->inst->folder . '.git/description');
    }
}
