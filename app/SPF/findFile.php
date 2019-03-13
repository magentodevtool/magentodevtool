<?php

namespace SPF;

function findFile($fileRx, $fileRxCond, $contentRx, $contentRxCond)
{
    $search = new FileSearch();
    $search->entityRegexp = $fileRx;
    $search->entityRegexpCond = $fileRxCond;
    $search->contentRegexp = $contentRx;
    $search->contentRegexpCond = $contentRxCond;
    return $search->run('.');
}

class FileSearch
{
    public $recursive = true;
    public $entityRegexp;
    public $entityRegexpCond = true;
    public $contentRegexp;
    public $contentRegexpCond = true;

    function run($dir, $currPath = '')
    {
        if (!is_dir($dir)) {
            return array();
        }
        if (!is_readable($dir)) {
            echo "\nDirectory \"$dir\" isn't readable\n";
            return array();
        }
        $currPath = $currPath !== '' ? rtrim($currPath, '/') . '/' : '';
        $files = array();
        foreach (scandir($dir) as $file) {
            if (in_array($file, array('.', '..'))) {
                continue;
            }
            $entityName = $currPath . $file;
            $entity = "$dir/$file";
            if ($this->recursive && is_dir($entity) && !is_link($entity)) {
                $files = array_merge($files, $this->run($entity, $entityName));
                continue;
            }
            if ($this->entityRegexp) {
                $rx = '~' . str_replace('~', "\\~", $this->entityRegexp) . '~';
                if ((bool)preg_match($rx, $entity) !== (bool)$this->entityRegexpCond) {
                    continue;
                }
            }
            if ($this->contentRegexp) {
                if (!is_readable($entity)) {
                    echo "\nFile \"$entity\" isn't readable\n";
                    return array();
                }
                $rx = '~' . str_replace('~', "\\~", $this->contentRegexp) . '~';
                if ((bool)preg_match($rx, file_get_contents($entity)) !== (bool)$this->contentRegexpCond) {
                    continue;
                }
            }
            $files[] = array(
                'name' => $entityName,
            );
        }
        return $files;
    }
}