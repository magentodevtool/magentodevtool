<?php

namespace Project\Installation\Magento\Module;

class Export
{
    /**
     * @var \Project\Installation $inst
     */

    protected $inst;

    protected $codePool;

    protected $package;

    protected $packagePath;

    protected $module;

    protected $srcDir;

    protected $destDir;

    protected $collectFilesDir;

    protected $log = array();

    protected $_configXML = false;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    function run($ARG)
    {

        $this->initVars($ARG);
        $this->checkSrcDirs();
        $this->checkDestDirs();

        $files = $this->collectFiles($this->srcDir);
        if (!count($files)) {
            throw new \Exception("Module Export Error: Nothing to export.");
        }

        $oldFiles = $this->collectFiles($this->destDir);
        $this->removeFiles($oldFiles);

        $this->copyFiles($files);

        return $this->log;
    }

    protected function initVars($ARG)
    {
        $modulePath = explode('_', $ARG->module);
        $this->module = array_pop($modulePath);
        $this->package = implode('_', $modulePath);
        $this->packagePath = str_replace('_', '/', $this->package);

        $this->codePool = $ARG->codePool;
        $this->srcDir = $this->inst->_docRoot;
        $this->destDir = substr($ARG->folder, strlen($ARG->folder) - 1) != '/' ? $ARG->folder . '/' : $ARG->folder;

        $this->addLog("Export module {$this->package}_{$this->module} to {$this->destDir}");
    }

    protected function checkSrcDirs()
    {
        if (!is_dir($this->srcDir)) {
            error("Source root directory does not exists: {$this->srcDir}");
        }

        if (!is_dir($moduleDir = $this->getModuleDir($this->srcDir))) {
            error("Source directory does not exists: {$moduleDir}");
        }
    }

    protected function checkDestDirs()
    {
        if (is_dir($this->destDir) && (!is_writable($this->destDir) || !is_executable($this->destDir))) {
            error("Destination directory is not writable: " . $this->destDir);
        }

        $foldersToCreate = array(
            $this->destDir . '/app/code/' . $this->codePool, //code dir,
            $this->destDir . '/app/etc/modules/' // $modules Xml Dir
        );

        foreach ($foldersToCreate as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            if (is_dir($dir) && (!is_writable($dir) || !is_executable($dir))) {
                error("Destination directory is not writable: " . $dir);
            }
        }
    }

    protected function collectFiles($collectFilesDir)
    {

        $this->collectFilesDir = $collectFilesDir;

        $files = array_merge(
            $this->getCodeFiles(),
            $this->getEtcFile(),
            $this->getTranslatesFiles(),
            $this->getLayoutsFiles(),
            $this->getTemplatesFiles(),
            $this->getJsCssFiles(),
            $this->getAdditionalFiles()
        );

        return $files;
    }

    protected function getCodeFiles()
    {
        $moduleDir = $this->getModuleDir($this->collectFilesDir);

        return listdir($moduleDir, true, true);
    }

    protected function getEtcFile()
    {
        return array($this->collectFilesDir . 'app/etc/modules/' . $this->package . '_' . $this->module . '.xml');
    }

    protected function getTranslatesFiles()
    {
        $_files = array();
        $moduleDir = $this->getModuleDir($this->collectFilesDir);

        $configXML = $this->getModuleConfigXML($moduleDir);
        $localePath = $this->collectFilesDir . 'app/locale/';
        if ($configXML && isset($configXML->frontend->translate)) {
            $translates = $configXML->xpath("*//translate//*//files/default");
            foreach ($translates as $translate) {
                if ($filePath = glob($localePath . "*/" . (string)$translate)) {
                    $_files = array_merge($_files, $filePath);
                }
            }
        }

        return array_unique($_files);
    }

    protected function getLayoutsFiles()
    {
        $_files = array();
        $moduleDir = $this->getModuleDir($this->collectFilesDir);

        $areas = array(
            'adminhtml' => $this->collectFilesDir . 'app/design/adminhtml/default/default/',
            'frontend' => $this->collectFilesDir . 'app/design/frontend/base/default/'
        );

        $configXML = $this->getModuleConfigXML($moduleDir);
        foreach ($areas as $areaName => $areaPath) {
            if ($configXML && isset($configXML->$areaName->layout->updates)) {
                $layoutUpdates = (array)$configXML->$areaName->layout->updates;
                foreach ($layoutUpdates as $layout) {
                    $layoutFile = (string)$layout->file;
                    $layoutFullName = $areaPath . 'layout/' . $layoutFile;
                    if (is_readable($layoutFullName)) {
                        $_files[] = $layoutFullName;
                    }
                }
            }
        }

        return $_files;
    }

    protected function getTemplatesFiles()
    {
        $_files = array();
        $modulePath = $this->getModuleStandardPath();

        $dirs = array(
            'app/design/adminhtml/default/default/template/',
            'app/design/frontend/base/default/template/',
        );

        foreach ($dirs as $dir) {
            $files = listdir($this->collectFilesDir . $dir . $modulePath, true, true);
            $_files = array_merge($_files, $files);
        }

        return $_files;
    }

    protected function getJsCssFiles()
    {

        $files = array();
        $modulePath = $this->getModuleStandardPath();

        $dirs = array(
            'skin/frontend/base/default/css/',
            'skin/frontend/base/default/js/',
            'js/'
        );

        foreach ($dirs as $dir) {
            $path = $this->collectFilesDir . $dir . $modulePath;
            $search = array($path, $path . '.js', $path . '.css');
            foreach ($search as $file) {
                if (is_file($file)) {
                    $files[] = $file;
                } elseif (is_dir($file)) {
                    $files = array_merge($files, listdir($file, true, true));
                }
            }
        }

        return $files;

    }

    protected function getAdditionalFiles()
    {

        $exportTxt = $this->collectFilesDir . "app/code/{$this->codePool}/{$this->packagePath}/{$this->module}/etc/export.txt";

        if (!is_readable($exportTxt)) {
            return array();
        }

        $patterns = file($exportTxt);
        $exportFiles = array();
        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if (!trim($pattern)) {
                continue;
            }
            $pattern = trim($pattern, '/');
            $path = $this->collectFilesDir . $pattern;
            if (is_file($path)) {
                $exportFiles[] = $path;
            } else {
                $glob = glob($path);
                if (count($glob)) {
                    foreach ($glob as $file) {
                        if (is_dir($file)) {
                            $exportFiles = array_merge($exportFiles, listdir($file, true, true));
                        } else {
                            $exportFiles[] = $file;
                        }
                    }
                } else {
                    // show warning only when collecting new files
                    if ($this->collectFilesDir === $this->srcDir) {
                        $this->addLog("Warning: nothing found by pattern \"$pattern\" from export.txt");
                    }
                }
            }
        }
        return $exportFiles;
    }

    protected function copyFiles($filesToCopy)
    {
        $copiedCount = 0;
        foreach ($filesToCopy as $file) {
            if ($this->copyFile($file)) {
                $copiedCount++;
            }
        }
        $this->addLog("$copiedCount new files have been copied");
    }

    protected function removeFiles($files)
    {
        $deletedCount = 0;
        foreach ($files as $file) {
            if (@unlink($file)) {
                $deletedCount++;
            }
        }
        $this->addLog("$deletedCount old files have been deleted");
    }

    protected function copyFile($file)
    {
        $destFile = str_replace($this->srcDir, $this->destDir . '/', $file);
        $destFileDir = dirname($destFile);
        if (!is_dir($destFileDir)) {
            mkdir($destFileDir, 0775, true);
        }
        return copy($file, $destFile);
    }

    protected function getModuleDir($dir)
    {
        return $dir . "app/code/{$this->codePool}/{$this->packagePath}/{$this->module}";
    }

    protected function getModuleStandardPath()
    {
        return strtolower($this->packagePath) . '/' . strtolower($this->module);
    }

    protected function getModuleConfigXML($moduleDir)
    {
        if (!$this->_configXML) {
            $this->_configXML = @simplexml_load_file($moduleDir . '/etc/config.xml');
        }

        return $this->_configXML;
    }

    protected function addLog($msg)
    {
        $this->log[] = $msg;
    }

}
