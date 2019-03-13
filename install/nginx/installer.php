<?php

interface IInstaller
{
    /**
     * @return bool
     */
    public function run();

    /**
     * @return IInstaller
     */
    public static function getInstance();
}

/**
 * Class Installer
 */
abstract class Installer implements IInstaller
{
    protected $phpVersion;
    protected $_cGood = "\033[1;32m";
    protected $_cBad = "\033[1;31m";
    protected $_cReset = "\033[0m";

    protected $_httpPort = '80';
    protected $_httpSafePort = '443';
    protected $_httpDomain = 'devtool.local';

    protected $_options = array();

    protected $_installCommands = array();

    protected $_missingPackages = array();

    protected static $_instance; // object instance

    /**
     * @return bool
     */
    public function run()
    {
        $this->readOptions();

        if ($this->isUsage()) {
            echo $this->usage();

            return false;
        }

        $this->runChecks();

        if ($this->isCheckOnly()) {
            return false;
        }

        return true;
    }

    /**
     * Write devtool host in /etc/hosts
     */
    protected function _registerHost()
    {
        $this->log("\nFixing /etc/hosts file");
        Hosts::setDomainIp($this->_httpDomain, getServiceLocalIp('nginx'));
        $this->log(' * done');
    }

    public function getMyOwner()
    {
        // detect user for dev tool
        $fileInfoCmd = 'ls -l ' . escapeshellarg(__FILE__) . '| awk \'{print $3, $4}\'';
        $userInfo = explode(' ', trim(`$fileInfoCmd`));

        if (count($userInfo) !== 2) {
            $this->fail('Error: Cannot detect user for devtool');
        }

        if ($userInfo[0] == 'root') {
            $this->fail('Error: Detected user is root. Change the file permission.');
        }

        return $userInfo;
    }

    /**
     * Check OS release
     *
     * @return boolean
     */
    public function checkRelease()
    {
        $this->log('Checking release');

        $distributorId = trim(`lsb_release -i -s`);

        $this->log(" * Distributor Id: $distributorId");

        return $distributorId == 'Ubuntu';
    }

    public function runChecks()
    {
        if (!$this->checkRelease()) {
            $this->fail('Only Ubuntu is supported');
        }

        if (!$this->isCheckOnly() && !$this->checkSuperUser()) {
            $this->fail('You should be a super user. Type "sudo php nginx.php"');
        }

        $this->checkExtensions();
    }

    public function log($message)
    {
        if (1 || $this->isVerbose()) {
            echo $message . PHP_EOL;
        }
    }

    public function complete()
    {
        $this->setVerbose(true);

        $this->log("\n{$this->_cGood}Installation was successful.{$this->_cReset}");

        $this->log("\nTry http://{$this->_httpDomain}" . ($this->_httpPort != 80 ? ":{$this->_httpPort}" : "") . "/\n");
    }

    public function fail($message)
    {
        $this->setVerbose(true);

        $this->log("\n{$this->_cBad}Installation failed!{$this->_cReset}");
        $this->log($message);

        exit(1);
    }

    public function executeCommands($commands)
    {
        foreach ($commands as $command) {
            $this->log($command . '... ');
            exec($command . " 2>&1", $o, $r);
            if ($r !== 0) {
                $this->fail(implode(', ', $o));
            }
            $this->log('Done');
        }
    }

    public function readOptions()
    {
        $this->_options = getopt('chv', array('check', 'help', 'verbose'));
    }

    public function isCheckOnly()
    {
        return isset($this->_options['c']) || isset($this->_options['check']);
    }

    public function isUsage()
    {
        return isset($this->_options['h']) || isset($this->_options['help']);
    }

    public function isVerbose()
    {
        return isset($this->_options['v']) || isset($this->_options['verbose']) || $this->isCheckOnly();
    }

    public function usage()
    {
        $file = pathinfo(__FILE__, PATHINFO_BASENAME);
        return <<<USAGE
Usage: sudo {$file} [options]

  -h, --help    This help
  -c, --check   Run check, but do not install
  -v, --verbose Show info

USAGE;
    }

    /**
     * @return bool
     */
    public function checkSuperUser()
    {
        return trim(`whoami`) === 'root';
    }

    /**
     * Set verbose mode on/off.
     *
     * @param boolean $verbose
     */
    public function setVerbose($verbose)
    {
        if ($verbose) {
            $this->_options['v'] = $this->_options['verbose'] = true;
        } else {
            unset($this->_options['v']);
            unset($this->_options['verbose']);
        }
    }

    public function generateInstallCommands()
    {
        if ($this->_missingPackages) {
            $this->_installCommands = array(
                "apt-get -y install " . implode(' ', $this->_missingPackages)
            );
            $activatingPackages = array_filter(array_keys($this->_missingPackages), 'is_string');
            if (count($activatingPackages)) {
                $this->_installCommands[] = "phpenmod "
                    . implode(
                        ' ',
                        array_filter(array_keys($this->_missingPackages), 'is_string')
                    );
            }
        }

        return $this->_installCommands;
    }

    /**
     * @return bool
     */
    public function checkExtensions()
    {
        $this->log('Checking installed PHP extensions');

        $requiredExtensions = $this->getPhpRequiredExtensions();

        foreach ($requiredExtensions as $requiredExtension => $packagesToInstall) {
            if (!extension_loaded($requiredExtension)) {
                $this->_missingPackages[$requiredExtension] = implode(' ', $packagesToInstall);
            }
        }

        if ($this->_missingPackages) {
            $this->log(" * Missing:\n\t" . implode("\n\t", $this->_missingPackages));
        }

        return !count($this->_missingPackages);
    }

    protected function getPhpRequiredExtensions()
    {
        if ($this->phpVersion <= '5') {
            return array(
                'gd' => array('php5-gd'),
                'mcrypt' => array('php5-mcrypt'),
                'pdo_mysql' => array('php5-mysql'),
                'curl' => array('php5-curl'),
                'ssh2' => array('libssh2-php'),
                'sqlite3' => array('sqlite3', 'php5-sqlite'),
                'json' => array('php5-json'),
            );
        }
        return array(
            'gd' => array('php-gd'),
            'pdo_mysql' => array('php-mysql'),
            'curl' => array('php-curl'),
            'ssh2' => array('php-ssh2'),
            'sqlite3' => array('php-sqlite3'),
            'json' => array('php-json'),
            'xml' => array('php-xml'),
            'mbstring' => array('php-mbstring'),
        );

    }
}
