<?php

# incspf exec
# incspf execCallback
# incspf git/getCurrentBranch
# incspf git/getDir

namespace SPF\git;

function getDiff($filter, $options = array(), $withMeta = false)
{
    $diff = new Diff();
    return $diff->get($filter, $options, $withMeta);
}

class Diff
{
    const FILE_COUNT_LIMIT = 500;
    protected $diff = array();
    protected $statuses = array();
    protected $fileDiff;
    protected $isContentStarted = false;
    protected $isContentLimitReached = false;
    protected $contentLinesCount = 0;
    protected $fileDiffHead = '';
    protected $notices = array();
    protected $options = array('ignoreSpaces' => '--ignore-all-space --ignore-blank-lines');

    public function get($filter, $options = array(), $withMeta = false)
    {
        // make sure cwd is repository root because all code below work only with path related to repo root
        $cwdSave = getcwd();
        chdir(getDir() . '..');

        try {

            // check for conflicts before 'git reset' otherwise it will change status for unmerged files to M
            try {
                \SPF\exec('git show MERGE_HEAD 2>&1');
            } catch (\Exception $e) {
            }

            if (isset($e)) {
                // everything ok, no unfinished merge if fail
                unset($e);
            } else {
                \SPF\error('Unfinished merge detected, commit or abort it');
            }

            \SPF\exec(array(
                // reset makes just changed .gitignore rules working
                'git reset',
                // add all changes to the index (A, M, D, R)
                'git add -A .',
                // remove zdevtool folder from index (useful on remote installations when other dev run RAI action)
                'git reset -- zdevtool public_html/zdevtool src/zdevtool pub/zdevtool',
            ));

            $gitDiffCmd = 'git diff ' . $this->getOptionsString($options) . ' -M ' . $filter;
            \SPF\execCallback($gitDiffCmd, array($this, 'processDiffChunk'));

            if (count($this->diff) < \SPF\git\Diff::FILE_COUNT_LIMIT) { // don't add last file 2 times in case of files limit exceeded
                $this->savePreviousFile();
            }

        } catch (\Exception $e) {
        }

        // restore cwd
        chdir($cwdSave);

        if (isset($e)) {
            throw $e;
        }

        if ($withMeta) {
            return (object)array(
                'notices' => $this->notices,
                'diff' => $this->diff,
            );
        } else {
            return $this->diff;
        }
    }

    protected function getOptionsString($options)
    {
        if (!$selectedOptions = array_filter((array)$options)) {
            return '';
        }

        return join(' ', array_intersect_key($this->options, $selectedOptions));
    }

    public function processDiffChunk($chunk, $isNewLine)
    {

        // it will limit content line to 8KB
        if (!$isNewLine) {
            return;
        }

        if (preg_match('~^diff --git (.+)$~', $chunk, $matches)) {

            // next file is started

            $this->savePreviousFile();

            $this->fileDiff = (object)array(
                'file' => null,
                'type' => null,
                'diff' => '',
            );
            if (count($this->diff) >= \SPF\git\Diff::FILE_COUNT_LIMIT) {
                $this->notices[] = "There are too many modified files. Only first " . \SPF\git\Diff::FILE_COUNT_LIMIT . " are shown";
                return false;
            }

            $this->fileDiffHead = $matches[1];
            $this->isContentStarted = false;
            $this->contentLinesCount = 0;
            $this->isContentLimitReached = false;

        } elseif (!$this->isContentStarted && preg_match('~^new file mode ~', $chunk, $ms)) {

            $this->fileDiff->type = 'A';

        } elseif (!$this->isContentStarted && preg_match('~^deleted file mode ~', $chunk, $ms)) {

            $this->fileDiff->type = 'D';

        } elseif (!$this->isContentStarted && preg_match('~^--- (.+?)\t?\n$~', $chunk, $ms)) {

            if ($ms[1] !== '/dev/null') {
                $file = $this->parseFileName($ms[1]);
                $file = preg_replace('~^a/~', '', $file);
                $this->fileDiff->file = $file;
            }

        } elseif (is_null($this->fileDiff->file) && preg_match('~^\\+\\+\\+ (.+?)\t?\n$~', $chunk, $ms)) {

            $file = preg_replace("~\t$~", '', $ms[1]); // remove strange tab at the end
            $file = $this->parseFileName($file);
            $file = preg_replace('~^b/~', '', $file);
            $this->fileDiff->file = $file;

        } elseif (preg_match('~^rename from (.+)~', $chunk, $ms)) {

            $this->fileDiff->type = 'R';
            $this->fileDiff->file = $this->parseFileName($ms[1]);

        } elseif (preg_match('~^rename to (.+)~', $chunk, $ms)) {

            $this->fileDiff->renamedTo = $this->parseFileName($ms[1]);

        } elseif (preg_match('~^Binary files (.+) and (.+) differ$~', $chunk, $ms)) {

            $fileStr = $ms[1] !== '/dev/null' ? $ms[1] : $ms[2];
            $file = $this->parseFileName($fileStr);
            $file = preg_replace('~^[ab]/~', '', $file);
            $this->fileDiff->file = $file;
            $this->fileDiff->diff = '\\ Binary file, no diff availbale';

        } elseif (preg_match('~^@@ ~', $chunk)) {

            // skip not needed meta lines e.g. "--- /dev/null", "+++ b/file.txt"

            $this->isContentStarted = true;
            $this->addChunkToDiff($chunk);

        } elseif ($this->isContentStarted) {

            // collect content lines

            if (!$this->isContentLimitReached) {

                $this->addChunkToDiff($chunk);
                $this->contentLinesCount++;

                if ($this->contentLinesCount > 1000) {
                    $this->fileDiff->diff .= '\\ Diff is too big, more than 1000 lines';
                    $this->isContentLimitReached = true;
                }

                if (strlen($this->fileDiff->diff) > 128 * 1024) {
                    $this->fileDiff->diff .= '\\ Diff is too big, more than 128KB';
                    $this->isContentLimitReached = true;
                }

            }

        } else {
            // we don't need these lines
        }

    }

    function savePreviousFile()
    {
        if (!isset($this->fileDiff->file)) {
            if (!empty($this->fileDiffHead)) {
                /*
                file name is not determined in case of new file with no changes e.g.
                    diff --git a/public_html/file.txt b/public_html/file.txt
                    new file mode 100644
                    index 0000000..e69de29
                so we have no "+++ b/public_html/file.txt" line, let try to get it from head "a/public_html/file.txt b/public_html/file.txt"
                */
                $fileStr = substr($this->fileDiffHead, 0, (strlen($this->fileDiffHead) - 1) / 2);
                $file = $this->parseFileName($fileStr);
                $file = preg_replace('~^a/~', '', $file);
                $this->fileDiff->file = $file;
            } else {
                // no parsed file yet (only first time)
                return;
            }
        }
        $this->fileDiff->type = $this->fileDiff->type ?: 'M';
        $this->diff[] = $this->fileDiff;
    }

    function parseFileName($str)
    {
        // we need to strip slash only before \ and " and replace \t to tab

        if (@$str[0] !== '"') {
            return $str;
        } else {
            $str = trim($str, $str[0]);
        }

        $result = '';
        $curr = null;
        $prev = null;
        $toUnEscape = array_fill_keys(array('\\', '"'), 1);
        for ($i = 0; $i < strlen($str); $i++) {
            $prev = $curr;
            $curr = $str[$i];
            if ($prev === '\\') {
                if (isset($toUnEscape[$curr])) {
                    $result .= $curr;
                    $curr = null;
                } elseif ($curr === "t") {
                    $curr = "\t";
                }
                continue;
            }
            $result .= $prev;
        }
        $result .= $curr;

        return $result;
    }

    function addChunkToDiff($chunk)
    {
        $this->fileDiff->diff .= mb_convert_encoding($chunk, 'UTF-8');
    }

}
