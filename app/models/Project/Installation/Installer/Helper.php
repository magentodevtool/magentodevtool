<?php

namespace Project\Installation\Installer;

/**
 * Class Helper
 * @package Project\Installation\Installer
 *
 * @method \Project\Installation inst()
 */
trait Helper
{

    function getDistFiles()
    {
        return array_merge(
            glob($this->inst()->_appRoot . '{,.}*.dist', GLOB_BRACE),
            glob($this->inst()->_appRoot . '*/{,.}*.dist', GLOB_BRACE),
            glob($this->inst()->_appRoot . 'app/etc/' . '{,.}*.dist', GLOB_BRACE)
        );
    }

    function getFilesToCopy()
    {

        $distFiles = $this->getDistFiles();

        $filesToCopy = array();
        foreach ($distFiles as $file) {
            $requiredFile = preg_replace('~\.dist$~', '', $file);
            if (!file_exists($requiredFile)) {
                $filesToCopy[] = $requiredFile;
            }
        }

        return $filesToCopy;

    }

    function getMissingBaseFiles()
    {

        if ($this->project->type === 'magento2') {
            $files = array('pub/index.php', 'var', 'app/etc/env.php');
        } else {
            $files = array('.htaccess', 'index.php', 'var', 'media', 'app/etc/local.xml');
        }

        $missingFiles = array();

        foreach ($files as $fileName) {
            $file = $this->_appRoot . $fileName;
            if (!file_exists($file)) {
                $missingFiles[] = $file;
            }
        }

        return $missingFiles;

    }

    function getFilesToChmod()
    {
        if ($this->inst()->project->type == 'magento2') {
            return $this->getM2FilesToChmod();
        }
        return $this->getM1FilesToChmod();
    }

    function getM1FilesToChmod()
    {
        $filesToCheck = array('var', 'media', 'includes');
        $filesToChmod = array();
        foreach ($filesToCheck as $file) {
            $file = $this->inst()->_appRoot . $file;
            if (file_exists($file) && !is_writable_by_other($file)) {
                $filesToChmod [] = $file;
            }
        }

        return $filesToChmod;

    }

    function getM2FilesToChmod()
    {
        $filesToCheck = ['var', 'pub/static', 'pub/media', 'app/etc', 'generated'];
        $filesToChmod = [];
        foreach ($filesToCheck as $file) {
            $file = $this->inst()->_appRoot . $file;
            if (file_exists($file) && !is_file_writable_by_user($file, 'www-data')) {
                $filesToChmod[] = $file;
            }
        }
        return $filesToChmod;
    }

    function getContinueButton($buttonText = 'Continue')
    {
        return template(
            'project/installation/installer/continueButton',
            array('buttonText' => $buttonText)
        );
    }

    function isDomainLocal($domain)
    {
        $ips = gethostbynamel($domain);
        if ($ips === false) {
            return false;
        }
        $webServerIp = $this->inst()->webServer->getLocalIp();
        foreach ($ips as $ip) {
            if ($ip === $webServerIp) {
                return true;
            }
        }
        return false;
    }

    function getApacheVirtualHostFileName()
    {
        return '/etc/apache2/sites-available/' . $this->inst()->domain . '.conf';
    }

    public function getLastWebServerType()
    {
        return \Vars::get(LDAP_USER, null, null, 'lastWebServerType');
    }

    public function setLastWebServerType($value)
    {
        \Vars::set(LDAP_USER, null, null, 'lastWebServerType', $value);
    }

    public function getDomainAvailbaleAssertFile()
    {
        $file = $this->_url . 'skin/frontend/base/default/favicon.ico';
        if ($this->project->type === 'magento2') {
            $pub = $this->_appRoot === $this->_docRoot ? 'pub/' : '';
            $file = $this->_url . "{$pub}errors/default/images/favicon.ico";
        }
        return $file;
    }

}
