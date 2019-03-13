<?php

namespace Project\Installation;

/**
 * Class Installer
 * @package Project\Installation
 *
 * @method \Project\Installation inst()
 */
trait Installer
{

    function getCheckers()
    {

        switch ($this->inst()->project->type) {
            case 'simple':
                $checkers = $this->getCheckersSimple();
                break;

            case 'magento1':
            case 'magento2':
                $checkers = $this->getCheckersMagento();
                break;

            default:
                $checkers = array();
        }

        return $checkers;
    }

    function Check()
    {
        if ($this->inst()->type == 'remote') {
            return true;
        }
        return $this->getNextFix() === false;
    }

    function getNextFix()
    {
        foreach ($this->getCheckers() as $checker => $checkerDesc) {
            if (!$this->$checker()) {
                $fixer = preg_replace('~^check~', 'fix', $checker);
                return $fixer;
            }
        }
        return false;
    }

    function getFixDescription($fix)
    {
        $checkers = $this->getCheckers();
        return $checkers[preg_replace('~^fix~', 'check', $fix)];
    }

    function checkRepo()
    {
        return is_dir($this->inst()->folder . '.git');
    }

    function fixRepo()
    {
        if (!is_dir($this->inst()->folder)) {
            @mkdir($this->inst()->folder, 0755, true);
        }
        if (!is_dir($this->inst()->folder . '.git')) {
            exec(cmd(
                'git clone %s %s',
                $this->inst()->project->repository->url,
                $this->inst()->folder),
                $output, $return);
            return $return === 0;
        }
        return $this->checkRepo();
    }

    function checkMagento()
    {
        if ($this->project->type === 'magento2') {
            $assertFile = 'app/bootstrap.php';
        } else {
            $assertFile = 'app/Mage.php';
        }
        return is_file($this->_appRoot . $assertFile);
    }

    function checkDistFiles()
    {

        return !count($this->inst()->getFilesToCopy());

    }

    function fixDistFiles()
    {

        foreach ($this->inst()->getFilesToCopy() as $dest) {
            $src = $dest . '.dist';
            exec(cmd('cp -r %s %s', $src, $dest));
        }

        return $this->checkDistFiles();

    }

    function checkBaseFiles()
    {
        return !count($this->inst()->getMissingBaseFiles());
    }

    function fixBaseFiles()
    {

        foreach ($this->inst()->getMissingBaseFiles() as $file) {
            $fileName = preg_replace('~^' . preg_quote($this->inst()->_docRoot) . '~', '', $file);
            switch ($fileName) {
                case '.htaccess':
                case 'index.php':
                    if (file_exists($file . '.sample')) {
                        copy($file . '.sample', $file);
                    }
                    break;
                case 'var':
                    mkdir($file);
                    file_put_contents($file . '/.htaccess', "Order deny,allow\nDeny from all");
                    break;
                case 'media':
                    mkdir($file);
                    break;
                case 'app/etc/local.xml':
                    if (file_exists($file . '.template')) {
                        $content = file_get_contents($file . '.template');
                        $content = preg_replace(
                            array(
                                '~<date>.*</date>~',
                                '~<key>.*</key>~',
                                '~<table_prefix>.*</table_prefix>~',
                                '~<frontName>.*</frontName>~',
                                '~\n\s*<session_save>.*</session_save>~',
                                '~\n\s*<pdoType>.*</pdoType>~',
                                '~\n\s*<type>.*</type>~',
                                '~\n\s*<model>.*</model>~',
                                '~\n\s*<initStatements>.*</initStatements>~'
                            ),
                            array(
                                '<date>' . date('r') . '</date>',
                                '<key>' . md5(rand(1000000, 9999999)) . '</key>',
                                '<table_prefix></table_prefix>',
                                '<frontName>admin</frontName>',
                                '',
                                '',
                                '',
                                '',
                                ''
                            ), $content);
                        file_put_contents($file, $content);

                    }
                    break;
            }
        }

        return $this->checkBaseFiles();

    }

    function checkRights()
    {
        if ($this->inst()->project->type == 'magento2') {
            return $this->checkM2Rights();
        }
        return $this->checkM1Rights();
    }

    function checkM1Rights()
    {
        if (count($this->inst()->getM1FilesToChmod())) {
            return false;
        }
        return true;
    }

    function checkM2Rights()
    {
        if (count($this->inst()->getM2FilesToChmod())) {
            return false;
        }
        return true;
    }

    function fixRights()
    {
        if ($this->inst()->project->type == 'magento2') {
            return $this->fixM2Rights();
        }
        return $this->fixM1Rights();
    }

    function fixM1Rights()
    {
        foreach ($this->inst()->getFilesToChmod() as $file) {
            exec(cmd('sudo chmod -R ugo+w %s', $file));
        }
        return $this->checkRights();
    }

    function fixM2Rights()
    {
        $user = escapeshellarg(USER);
        $nginxUser = 'www-data';
        $commandsList = [
            'sudo find %s -type f -print0 | sudo xargs -0 setfacl -m ' . $nginxUser . ':rw -m g:' . $nginxUser . ':rw',
            'sudo find %s -type d -print0 | sudo xargs -0 setfacl -m ' . $nginxUser . ':rwx -m g:' . $nginxUser . ':rwx -m d:' . $nginxUser . ':rwx -m d:g:' . $nginxUser . ':rwx',
            'sudo setfacl -m d:' . $nginxUser . ':rwx -m d:g:' . $nginxUser . ':rwx %s',
            'sudo find %s -type f -print0 | sudo xargs -0 setfacl -m ' . $user . ':rw',
            'sudo find %s -type d -print0 | sudo xargs -0 setfacl -m ' . $user . ':rwx -m d:' . $user . ':rwx',
            'sudo setfacl -m d:' . $user . ':rwx %s'
        ];

        $filesToChmod = $this->inst()->getFilesToChmod();
        if (!count($filesToChmod)) {
            return;
        }
        $filesToChmod = array_map('escapeshellarg', $filesToChmod);
        $filesToChmod = implode(" ", $filesToChmod);

        foreach ($commandsList as &$command) {
            $command = sprintf($command, $filesToChmod);
            $o = $r = null;
            exec("$command 2>&1", $o, $r);
            if ($r !== 0) {
                error('Following command failed: ' . $command . "\nError message: " . implode("\n", $o));
            }
        }
        return $this->checkRights();
    }

    function checkDbXmlFile()
    {
        return (bool)$this->inst()->getDbXmlFile();
    }

    function checkDbCredentials()
    {
        if ($this->inst()->webServer->type === 'docker') {
            return $this->checkDbCredentialsDocker();
        } else {
            return $this->checkDbCredentialsDefault();
        }
    }

    protected function checkDbCredentialsDocker()
    {
        $appCred = $this->inst()->getDbCredentials('app');
        $credForApp = $this->inst()->getDbCredentialsForApp();

        // make sure host, username, password are correct
        $cmpFields = ['host' => 1, 'username' => 1, 'password' => 1];
        $appCredCmp = array_intersect_key((array)$appCred, $cmpFields);
        $credForAppCmp = array_intersect_key((array)$credForApp, $cmpFields);
        if ($appCredCmp !== $credForAppCmp) {
            return false;
        }

        $externalCred = $this->inst()->getDbCredentials();
        return $this->inst()->checkExternalDbCredentials($externalCred);
    }

    protected function checkDbCredentialsDefault()
    {
        $cred = $this->inst()->getDbCredentials();
        if (!$this->checkExternalDbCredentials($cred)) {
            return false;
        }
        $dbUsedBy = $this->inst()->findInstallationsByDbName($cred->dbname);
        return count($dbUsedBy) === 0;
    }

    function checkExternalDbCredentials($cred)
    {
        if (!\Mysql::server($cred)) {
            return false;
        }
        if (!\Mysql::db($cred->dbname)) {
            \Mysql::createDb($cred->dbname);
            if (!\Mysql::db($cred->dbname)) {
                return false;
            }
        }
        return true;
    }

    function fixDbCredentials()
    {
        if ($this->inst()->webServer->type !== 'docker') {
            // dialog will be shown
            return false;
        }

        // set Docker DB credentials
        $cred = $this->inst()->getDbCredentialsForApp();
        $this->inst()->setDbCredentials($cred);
        return $this->checkDbCredentials();
    }

    function checkDbData()
    {
        $cred = $this->inst()->getDbCredentials();
        return \Mysql::server($cred) && \Mysql::db($cred->dbname) && \Mysql\Db::isMagento();
    }

    function checkBaseUrls()
    {
        $sql = 'select * from core_config_data where path = "web/cookie/cookie_domain" and value is not null';
        if (\Mysql::query($sql)->rowCount()) {
            return false;
        }

        return !count($this->inst()->getBaseURLDomainsToReplace());
    }

    function checkBaseUrlsSsl()
    {
        if ($this->inst()->webServer->getConfig()->SSL) {
            return true;
        }
        $httpsBaseUrlsCount = \Mysql::query(
            'select * from core_config_data where path like "web/%secure/base%url" and value like "https%"'
        )->rowCount();
        return !$httpsBaseUrlsCount;
    }

    function fixBaseUrls()
    {

        $domainsToReplace = $this->inst()->getBaseURLDomainsToReplace();
        foreach ($domainsToReplace as $domain) {
            \Mysql::query(
                'update core_config_data set value = replace(value, ?, ?)
                 where path like "web/%secure%url" and value like "http%";',
                array(
                    '/' . $domain . '/',
                    '/' . $this->inst()->domain . '/'
                ));
        }

        \Mysql::query('delete from core_config_data where path = "web/cookie/cookie_domain"');

        return $this->checkBaseUrls();
    }

    function fixBaseUrlsSsl()
    {
        // in case of direct call from db switch and db import from installer
        if ($this->inst()->webServer->type === 'nginx-php-fpm') {
            return true;
        }
        if ($this->inst()->webServer->getConfig()->SSL) {
            return true;
        }
        \Mysql::query(
            'update core_config_data set value = replace(value, "https://", "http://")
             where path like "web/%secure/base%url" and value like "https%"'
        );
        return $this->checkBaseUrlsSsl();
    }

    function checkDomainInHosts()
    {
        return (\Hosts::getDomainIp($this->inst()->domain) === $this->inst()->webServer->getLocalIp());
    }

    function fixDomainInHosts()
    {
        \Hosts::setDomainIp($this->inst()->domain, $this->inst()->webServer->getLocalIp());
        return $this->checkDomainInHosts();
    }

    function checkWebServerType()
    {
        return in_array($this->inst()->webServer->type, array('apache', 'nginx-php-fpm', 'docker'))
            && $this->inst()->checkDockerServerType();
    }

    function checkCustomMainDomain()
    {
        if (!$this->inst()->vars->get('customMainDomain')) {
            return false;
        }

        // domain can be *.dev OR *.dev.corp.ism.nl OR *.sl-dev.corp.ism.nl OR *.local
        // todo: validate if domain is local then it must ends with '.dev'
        return true;
    }

    function checkApacheVirtualHostExistence()
    {
        return (bool)$this->inst()->webServer->getConfig();
    }

    function fixApacheVirtualHostExistence()
    {

        // if magento is placed right in repo root
        if ($this->inst()->folder === $this->inst()->_docRoot) {
            $logsDir = '/var/log/apache2/';
            $instKey = $this->inst()->getKey();
            $logsAccessFile = "access.$instKey.log";
            $logsErrorFile = "error.$instKey.log";
        } else {
            $logsDir = $this->inst()->folder . 'logs/';
            if (!is_dir($logsDir)) {
                umask(0);
                mkdir($logsDir, 0777);
            }
            $logsAccessFile = 'access.log';
            $logsErrorFile = "error.log";
        }

        $directoryAccess = "Order allow,deny\n\t\tAllow from all";
        if (version_compare('2.4.0', getApacheVersion()) === -1) {
            $directoryAccess = "Require all granted";
        }

        $virtualHostName = getApacheNameVirtualHost();

        $virtualHost = <<<vhost
<VirtualHost $virtualHostName>
	ServerName {$this->inst()->domain}
	DocumentRoot "{$this->inst()->_docRoot}"
	<Directory "{$this->inst()->_docRoot}">
		Options +Indexes +FollowSymLinks -MultiViews
		AllowOverride All
		$directoryAccess
	</Directory>
	CustomLog "{$logsDir}{$logsAccessFile}" combined
	ErrorLog "{$logsDir}{$logsErrorFile}"
</VirtualHost>
vhost;

        $virtualHostFile = $this->inst()->getApacheVirtualHostFileName();
        sudo_file_put_contents($virtualHostFile, $virtualHost);
        exec(cmd('sudo ln -s %s %s', $virtualHostFile, '/etc/apache2/sites-enabled/' . basename($virtualHostFile)));
        reloadApache();

        return $this->checkApacheVirtualHostExistence();

    }

    function checkApacheVirtualHostDuplicate()
    {
        $apacheConfig = $this->inst()->webServer->getConfig();
        if (preg_match_all('~(<VirtualHost[^>]*>)~ism', $apacheConfig->fileText, $ms)) {
            return count($ms[1]) <= 1;
        }
        return true;
    }

    function checkApacheServerName()
    {
        // check if server name is unique and is equal to inst->domain
        $allServerNames = array();
        foreach (\listdir('/etc/apache2/sites-enabled', true) as $file) {
            $content = stripSharpComments(file_get_contents($file));
            if (
                preg_match('~\n\s*ServerName\s+([^\s]+)\s~sm', $content, $ms)
                && !preg_match('~\sSSLEngine\s+on\s~ism', $content)
            ) {
                $allServerNames[$ms[1]] = isset($allServerNames[$ms[1]]) ? ++$allServerNames[$ms[1]] : 1;
            }
        }
        if (!isset($allServerNames[$this->inst()->domain])) {
            return false;
        }
        return $allServerNames[$this->inst()->domain] < 2;
    }

    function checkDomainAvailable()
    {
        $assertFile = $this->inst()->getDomainAvailbaleAssertFile();
        return (bool)@file_get_contents_unsecure($assertFile);
    }

    function checkGitIsInstalled()
    {
        return isGitInstalled();
    }

    function checkRepoUrl()
    {
        $repoUrl = $this->inst()->exec('git config remote.origin.url');
        if ($this->inst()->project->repository->url !== $repoUrl) {
            return false;
        }
        return true;
    }

    function checkApacheIsRunning()
    {
        return count(getServiceListening('apache2')) > 0;
    }

    function fixApacheIsRunning()
    {
        exec('sudo service apache2 start');
        return $this->checkApacheIsRunning();
    }

    function checkApacheNameVirtualHost()
    {
        return getApacheNameVirtualHost() === $this->inst()->webServer->getConfig()->NameVirtualHost;
    }

    function fixApacheNameVirtualHost()
    {
        $apacheConfig = $this->inst()->webServer->getConfig();
        $fixedConfig = preg_replace(
            '~<VirtualHost[^>]*>~ism', '<VirtualHost ' . getApacheNameVirtualHost() . '>',
            $apacheConfig->fileText
        );
        $apacheConfig->save($fixedConfig);
        return $this->checkApacheNameVirtualHost();
    }

    function checkApacheVirtualHostDocRoot()
    {
        return count($this->inst()->webServer->getConfig()->files) <= 1;
    }

    function checkApacheVirtualHostAliases()
    {

        /*
         * if project already installed but just added into devtool
         * try to find additional local domains in database that must be added to apache aliases
        */

        // if no database do nothing
        if (!$this->checkDbCredentials() || !\Mysql\Db::isMagento()) {
            return true;
        }

        return !count($this->inst()->getAdditionalApacheAliases());

    }

    function fixApacheVirtualHostAliases()
    {

        $apacheConfig = $this->inst()->webServer->getConfig();
        $aliases = array_merge($apacheConfig->ServerAlias, $this->inst()->getAdditionalApacheAliases());

        $apacheConfigText = explode("\n", $apacheConfig->fileText);

        // remove ServerAlias lines
        foreach ($apacheConfigText as $key => $line) {
            if (preg_match('~\s+ServerAlias~i', $line)) {
                unset($apacheConfigText[$key]);
            }
        }
        $apacheConfigText = implode("\n", $apacheConfigText);

        // set aliases right after ServerName
        $apacheConfigText = preg_replace(
            '~(\n[ \t]*ServerName.*?\n)~ism',
            "\$1\tServerAlias " . implode(' ', $aliases) . "\n",
            $apacheConfigText
        );

        $apacheConfig->save($apacheConfigText);

        return $this->checkApacheVirtualHostAliases();

    }

    function checkDomainValid()
    {
        return in_array($this->inst()->domain, $this->inst()->webServer->getDomains());
    }

    /**
     * @return array
     */
    public function getCheckersSimple()
    {
        return array(
            'checkGitIsInstalled' => 'install Git',
            'checkRepo' => 'clone sources',
            'checkRepoUrl' => 'fix repository url',
        );
    }

    /**
     * @return array
     */
    public function getCheckersMagento()
    {
        return array_merge(
            $this->getSourceCheckers(),
            $this->getWebServerCheckers(),
            $this->getDatabaseCheckers()
        );
    }

    function getSourceCheckers()
    {
        return array(
            'checkGitIsInstalled' => 'install Git',
            'checkRepo' => 'clone sources',
            'checkRepoUrl' => 'fix repository url',
            'checkComposer' => 'composer install',
            'checkMagento' => 'find magento',
            'checkDistFiles' => 'copy dist files',
            'checkBaseFiles' => 'restore base files',
            'checkRights' => 'fix rights',
            'checkScss' => 'compass compile',
        );
    }

    function getWebServerCheckers()
    {
        $checkers = array(
            'checkWebServerType' => 'select server type',
        );

        switch ($this->inst()->webServer->type) {
            case 'nginx-php-fpm':
                $checkers = array_merge($checkers, $this->getNginxFpmCheckers());
                break;
            case 'apache':
                $checkers = array_merge($checkers, $this->getApacheCheckers());
                break;
            case 'docker':
                $checkers = array_merge($checkers, $this->getDockerCheckers());
                break;
        }

        return $checkers;
    }

    function getNginxFpmCheckers()
    {
        return array(
            // nginx part
            'checkPhpFpmInstalled' => 'install PHP-FPM',
            'checkFpmMagentoFiles' => 'install PHP-FPM Magento config files',
            'checkNginxInstalled' => 'install nginx',
            'checkNginxMagentoFiles' => 'install nginx Magento config files',
            'checkNginxIsRunning' => 'run nginx',
            'checkDomainInHosts' => 'add/modify host into /etc/hosts',
            'checkNginxServer' => 'add nginx server definition',
        );
    }

    function getApacheCheckers()
    {
        return array(
            'checkApacheIsRunning' => 'run apache',
            'checkDomainInHosts' => 'add/modify host into /etc/hosts',
            'checkCustomMainDomain' => 'setup custom main domain',
            'checkApacheVirtualHostExistence' => 'add apache virtual host',
            'checkApacheVirtualHostDocRoot' => 'fix virtual host document root',
            'checkApacheServerName' => 'fix virtual host server name',
            //'checkApacheVirtualHostDuplicate' => 'fix duplicate virtual host',
            'checkApacheNameVirtualHost' => 'fix virtual host name',
            'checkApacheVirtualHostAliases' => 'add apache virtual host aliases',
            'checkDomainValid' => 'fix domain',
            'checkDomainAvailable' => 'fix domain',
        );
    }

    function getDatabaseCheckers()
    {
        if ($this->project->type === 'magento2') {
            $checkers = array(
                'checkDbCredentials' => 'fix database credentials',
                'checkDbData' => 'import database',
                'checkBaseUrls' => 'fix base urls',
            );
        } else {
            $checkers = array(
                'checkDbXmlFile' => 'fix db xml file',
                'checkDbCredentials' => 'fix database credentials',
                'checkDbData' => 'import database',
                'checkBaseUrls' => 'fix base urls',
            );
        }

        if ($this->inst()->webServer->type != 'nginx-php-fpm') {
            $checkers['checkBaseUrlsSsl'] = 'fix SSL base URLs';
        }

        return $checkers;
    }

    function checkComposer()
    {
        $composer = $this->inst()->generation->composer;
        if (!$composer->isAvailable()) {
            return true;
        }
        return $composer->wasDone();
    }

    function fixComposer()
    {
        try {
            $this->inst()->generation->composer->run();
            return $this->checkComposer();
        } catch (\Exception $e) {
            return false;
        }
    }

    function checkScss()
    {
        if (
            !$this->inst()->generation->scss->isAvailable()
            || !count($this->inst()->generation->scss->getThemes())
        ) {
            return true;
        }
        $repoCloneTime = $this->inst()->git->getRepoCloneTime();
        $scssCompileTime = $this->inst()->vars->get('installer/scssCompileTime');
        $compiled = $scssCompileTime > $repoCloneTime;
        return $compiled;
    }

    function fixScss()
    {
        try {
            $this->inst()->generation->scss->run();
            $this->inst()->vars->set('installer/scssCompileTime', time());
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}