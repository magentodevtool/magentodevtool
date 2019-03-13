<?php

#incspf exec
#incspf error
#incspf listdir2

namespace SPF\config;

function backup($comment)
{
    $backup = new Backup();
    $backup->run($comment);
}

class Backup
{
    protected $repositoryDir;

    protected $systemFiles = array(
        '/etc/nginx/',
        '/etc/apache2/',
        '/etc/cron.d/',
        '/etc/httpd/',
        '/etc/php5/',
    );

    protected $applicationFiles = array(
        'index.php',
        '.htaccess',
        'app/etc/',
    );

    protected $config = array(
        'ignore' => array(
            'files' => array(),
            'directories' => array(),
        ),
    );

    protected $logFile = 'log.txt';

    function run($comment)
    {
        try {
            $this->initConfig();
            $this->initBackupRepository();
            $this->removeFiles();
            $this->copySystemFiles();
            $this->copyApplicationFiles();
            $this->copyAdditionalFiles();
            $this->fixRights(); // - fix rights before commit to have changes committed
            $this->commit(trim($comment));
            $this->fixRights(); // - fix right after commit to have files in .git folder fixed
        } catch (\Exception $e) {
            $this->log($e->getMessage());
            try {
                $this->reset();
            } catch (\Exception $e2) {
            }
            throw $e;
        }
    }

    protected function initConfig()
    {
        $configFile = 'var/backups/config/config.json';
        if (!file_exists($configFile)) {
            return;
        }
        $configJson = file_get_contents($configFile);
        if (!$configJson) {
            \SPF\error("Failed to load $configFile");
        }
        $config = \json_decode($configJson, true);
        if (!is_array($config)) {
            \SPF\error("Failed to parse $configFile, array expected");
        }
        $this->config = array_merge_recursive($this->config, $config);
    }

    protected function initBackupRepository()
    {
        $this->docRoot = realpath(getcwd()) . '/';
        $dir = $this->docRoot . 'var/backups/config/';
        $this->createDir($dir);

        if (!is_dir($dir . '/' . '.git')) {
            \SPF\exec(
                array(
                    'cd %s',
                    'git init',
                    'git config user.name devtool',
                    'git config user.email devtool@email.com',
                    'echo %s > .gitignore',
                    'if [ `which setfacl` ]; then setfacl -R -m g::rwX -m u::rwX .; else chmod -R ug+rwX .; fi',
                    'git add .gitignore',
                    'git commit -m "init"'
                ),
                $dir,
                $this->logFile
            );
        }

        $this->repositoryDir = $dir;
    }

    protected function removeFiles()
    {
        \SPF\exec(
            array(
                'rm -rf %s',
                'rm -rf %s',
            ),
            $this->repositoryDir . 'system/',
            $this->repositoryDir . 'application/'
        );
    }

    protected function copySystemFiles()
    {
        $this->copy($this->systemFiles, 'system/');
    }

    protected function copyApplicationFiles()
    {
        $this->copy($this->applicationFiles, 'application/', $this->docRoot);
    }

    protected function copyAdditionalFiles()
    {
        $src = $this->getAdditionalFiles();

        $sys = $app = array();
        foreach ($src as $file) {
            if ($file[0] === '/') {
                $sys[] = $file;
            } else {
                $app[] = $file;
            }
        }

        $this->copy($sys, 'system/', $docRoot = false);
        $this->copy($app, 'application/', $this->docRoot);
    }

    protected function fixRights()
    {
        try {
            \SPF\exec(
                'if [ `which setfacl` ]; then setfacl -R -m u::rwX -m g::rwX %s; else chmod -R ug+rwX %s; fi',
                $this->repositoryDir,
                $this->repositoryDir
            );
        } catch (\Exception $e) {
            // skip error, it happens if repositoryDir have files with different owners, however it fix rights for files which were created by current user
        }
    }

    protected function commit($comment)
    {
        try {
            \SPF\exec(
                array(
                    'cd %s',
                    'git add -A .',
                    'git commit -m %s',
                ),
                $this->repositoryDir,
                $comment
            );
        } catch (\Exception $e) {

            $nothingToCommitMsg = 'nothing to commit';
            if (strpos($e->getMessage(), $nothingToCommitMsg)) {
                $this->log($comment . ' (' . $nothingToCommitMsg . ')');

                return;
            }

            $this->log($e->getMessage());
            throw $e;
        }

        $this->log($comment);
    }

    protected function copy($sources, $destination, $docRoot = false)
    {
        foreach ($sources as $src) {
            $dest = str_replace('//', '/', $this->repositoryDir . $destination . $src);
            $src = $docRoot ? $docRoot . $src : $src;

            if (!file_exists($src)) {
                continue;
            }

            if (is_dir($src)) {
                $this->createDir($dest);
            } else {
                $this->createDir(dirname($dest));
            }

            $this->copyFiles($src, $dest);
        }
    }

    // copy files instead of symlinks and ignore invalid symlinks (not possible with cp and rsync)
    protected function copyFiles($src, $dest)
    {
        if (is_file($src)) {
            if ($this->doIgnoreFile($src)) {
                return;
            }
            if (!copy($src, $dest)) {
                \SPF\error("Failed to copy $src to $dest");
            }
            return;
        }

        if (!is_dir($src)) {
            \SPF\error('Source not found');
        }
        if (!is_dir($dest)) {
            \SPF\error('Destination should be a directory');
        }

        $files = \SPF\listdir2($src, array(
            'recursive' => true,
            'ignoreDirectories' => $this->config['ignore']['directories'],
        ));
        $dest = rtrim($dest, '/') . '/';
        $src = rtrim($src, '/') . '/';

        $errors = array();
        foreach ($files as $file) {
            $srcOrigPath = $src . $file;
            if ($this->doIgnoreFile($srcOrigPath)) {
                continue;
            }
            if (!$srcRealPath = realpath($srcOrigPath)) {
                continue;
            }
            $destFile = $dest . $file;
            if (strpos($destFile, '/') !== false) {
                $destDir = dirname($destFile);
                if (!is_dir($destDir) && !mkdir($destDir, 0777, true)) {
                    \SPF\error("Failed to create directory $destDir");
                }
            }
            if (!copy($srcRealPath, $destFile)) {
                $errors[] = "Failed to copy $srcOrigPath to $destFile";
            }
        }
        if (\count($errors)) {
            \SPF\error(implode("\n", $errors));
        }
    }

    protected function doIgnoreFile($file)
    {
        return in_array($file, $this->config['ignore']['files']);
    }

    protected function getAdditionalFiles()
    {
        $file = $this->repositoryDir . 'files.txt';

        if (!is_readable($file)) {
            return array();
        }

        $sources = array();
        $patterns = file($file);
        foreach ($patterns as $pattern) {
            $sources[] = trim($pattern);
        }

        return $sources;
    }

    protected function createDir($dir)
    {
        if (is_dir($dir)) {
            return;
        }
        umask(0);
        if (!mkdir($dir, 0775, true)) {
            \SPF\error("Can't create $dir folder");
        }
    }

    protected function log($comment)
    {
        $logFile = $this->repositoryDir . $this->logFile;

        if (!file_exists($logFile)) {
            touch($logFile);
            \SPF\exec(
                'if [ `which setfacl` ]; then setfacl -R -m g::rwX -m u::rwX %s; else chmod -R ug+rwX %s; fi',
                $logFile, $logFile
            );
        }

        file_put_contents($logFile, "\n" . date('Y-m-d H:i:s') . " - " . $comment, FILE_APPEND);
    }

    protected function reset()
    {
        if (!is_dir($this->repositoryDir . '.git')) {
            // if no backup repo it will do reset for app repo but shouldn't
            return;
        }

        \SPF\exec(
            array(
                'cd %s',
                'git reset --hard',
                'git clean -fd', // remove untracked filed & dirs
            ),
            $this->repositoryDir
        );
    }
}
