<?php

namespace Project\Installation;

class Rsync
{

    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    public function run($remoteInst, $sources, $destination)
    {
        $listFile = TMP_DIR . 'sync' . ucfirst($destination) . '.' . $this->inst->project->name . '.list';

        $sources = (array)$sources;
        $destination .= (substr($destination, -1) == '/' ? '' : '/');
        $localDestDir = $this->inst->_docRoot . $destination;
        $remoteDestDir = $remoteInst->_docRoot . $destination;

        if (!is_dir($localDestDir)) {
            umask(0);
            mkdir($localDestDir, 0777, true);
        }

        file_put_contents($listFile, implode("\r", $sources));

        // use shellescapef instead of cmd to avoid adding 2>&1
        $cmd = shellescapef(
            "rsync --checksum --recursive --human-readable --verbose --compress --prune-empty-dirs --links"
            . " --progress --no-perms --omit-dir-times --exclude 'catalog/product/cache' --files-from=%s %s@%s:%s %s &",
            $listFile, $remoteInst->login, $remoteInst->host, $remoteDestDir, $localDestDir
        );

        $totalFiles = 0;
        $processedFiles = 0;
        $progressInfoKey = 'imageImport/progressInfo';

        // reset progress info
        $this->inst->vars->set($progressInfoKey, null);

        execCallback($cmd,
            function ($chunk, $isNewLine) use (&$totalFiles, &$processedFiles, &$progressInfoKey) {
                if ($isNewLine) {
                    if (!$totalFiles) {
                        if (strpos($chunk, 'files to consider')) {
                            $totalArr = explode("\r", $chunk);
                            $totalFiles = (int)end($totalArr);
                        }
                    } else {
                        if (strpos($chunk, '     ') == 0) {
                            $processedFiles += 1;
                            $percents = number_format(($processedFiles * 100) / $totalFiles, 2);
                            $this->inst->vars->set($progressInfoKey, $percents);
                        }
                    }
                }
            }
        );

        unlink($listFile);

        return true;
    }

}
