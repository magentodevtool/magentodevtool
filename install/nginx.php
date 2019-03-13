#!/usr/bin/env php
<?php

require_once dirname(__DIR__) . '/app/core/functions.php';
require_once __DIR__ . '/nginx/installer.php';

class NginxInstaller extends Installer
{
    protected $_fpmPool = 'devtool';
    protected $_fpmPoolDirectory;

    protected $_nginxSitesAvailableDirectory = '/etc/nginx/sites-available/';
    protected $_nginxSitesEnabledDirectory = '/etc/nginx/sites-enabled/';
    protected $_nginxSiteFileName = 'devtool.local.conf';

    protected $_fpmListenSocket = '/var/run/devtool.sock';

    protected $_apacheAutorunLineOld = 'apache2 -f /etc/apache2/apache2.devtool.conf';
    protected $_apacheAutorunLine = 'apachectl -f /etc/apache2/apache2.devtool.conf';

    protected $_apacheRestartRequired = false;
    protected $_nginxRestartRequired = false;
    protected $_fpmRestartRequired = false;

    protected $sslCertificateFile = '/etc/ssl/certs/ssl-cert-snakeoil.pem';

    protected $sslKeyFile = '/etc/ssl/private/ssl-cert-snakeoil.key';

    public function __construct()
    {
        if ($this->getUbuntuVersion() < '16.04') {
            $phpv = $this->phpVersion = '5';
        } else {
            $files = scandir('/etc/php');
            if (!$files) {
                $this->fail('Can\'t determine PHP version');
            }
            $phpv = $this->phpVersion = end($files);
        }
        $this->_fpmPoolDirectory = '/etc/' . ($phpv === '5' ? 'php5' : "php/$phpv") . '/fpm/pool.d/';
    }

    protected function getUbuntuVersion()
    {
        exec('lsb_release -a 2>&1', $o, $r);
        $o = implode("\n", $o);
        if ($r !== 0) {
            throw new Exception('Ubuntu version wasn\'t detected: ' . $o);
        }
        if (!preg_match('~Release:\s+([0-9.]+)~ism', $o, $matches)) {
            throw new Exception('Ubuntu version wasn\'t detected');
        }
        return $matches[1];

    }

    /**
     * @return Installer
     */
    public static function getInstance()
    {
        if (!isset(self::$_instance)) {
            $class = __CLASS__;
            self::$_instance = new $class();
            return self::$_instance;
        }
        return self::$_instance;
    }

    public function run()
    {
        if (!parent::run()) {
            return false;
        }

        $this->restartServices();

        $this->_registerHost();
        $this->_createFpmPool();
        $this->_createDevToolNginxServer();

        $this->restartServices();

        $this->complete();
    }

    /*
     * FUNCTIONS
     */

    /**
     * Register FPM pool
     */
    protected function _createFpmPool()
    {
        $fpmPoolFileName = $this->_fpmPoolDirectory . $this->_fpmPool . '.conf';

        $this->log("\nChecking PHP-FPM pool file [$fpmPoolFileName]");

        list($devUser, $devGroup) = $this->getMyOwner();
        $devUserQuoted = fpm_quote($devUser);
        $devGroupQuoted = fpm_quote($devGroup);

        $nginxOwner = getNginxOwner();
        $socketUserQuoted = fpm_quote($nginxOwner['user']);
        $socketGroupQuoted = fpm_quote($nginxOwner['group']);

        $poolConfig = <<<POOLCONFIG
[$this->_fpmPool]

user = $devUserQuoted
group = $devGroupQuoted

listen.owner = $socketUserQuoted
listen.group = $socketGroupQuoted
listen = $this->_fpmListenSocket

pm = dynamic

; max requests concurrency
pm.max_children = 80

; min fpm instances which should be available always
pm.start_servers = 10

; min additional instances if pm.start_servers aren't enough to handle all requests
pm.min_spare_servers = 5

; max additional instances if pm.start_servers is not enough to handle all requests
pm.max_spare_servers = 15

php_value[memory_limit] = 1G
php_value[error_reporting] = E_ALL|E_STRICT
php_value[max_execution_time] = 0
php_value[max_input_time] = -1
php_value[short_open_tag] = 1
php_value[display_errors] = 1
php_value[log_errors] = 1

; disable user agent verification to not break multiple image upload
php_value[suhosin.session.cryptua] = 0

; turn off compatibility with PHP4 when dealing with objects
php_value[zend.ze1_compatibility_mode] = 0

; Disable APC
php_value[apc.enabled] = 0
php_value[apc.cache_by_default] = 0

POOLCONFIG;

        $this->log("Creating new PHP-FPM pool file.");
        file_put_contents($fpmPoolFileName, $poolConfig);

        $this->_fpmRestartRequired = true;

        $this->log('Done');
    }

    protected function _createDevToolNginxServer()
    {
        if (!file_exists($this->_nginxSitesAvailableDirectory)) {
            mkdir($this->_nginxSitesAvailableDirectory);
        }
        $nginxSiteFileName = $this->_nginxSitesAvailableDirectory . $this->_nginxSiteFileName;
        $httpIp = getServiceLocalIp('nginx');

        $this->log("\nChecking nginx server file [$nginxSiteFileName]");

        $documentRoot = realpath(__DIR__ . '/..');

        $nginxServerDefinition = <<<NGINX
server {
    listen $httpIp:$this->_httpPort;
    listen $httpIp:$this->_httpSafePort ssl;

    server_name $this->_httpDomain;
    root $documentRoot;

    ssl_certificate /etc/ssl/certs/ssl-cert-snakeoil.pem;
    ssl_certificate_key /etc/ssl/private/ssl-cert-snakeoil.key;

    access_log /var/log/nginx/access.devtool.log;
    error_log /var/log/nginx/error.devtool.log;

    # Copy of request URI to be used as REQUEST_URI for fastCGI parameter
    set \$sub_request_uri \$request_uri;

    # Accept connections only for domains, located on this server, and send 444 if request address is different
    if (\$host !~ ^($this->_httpDomain)$ ) {
        return 444;
    }

    # Limit methods, allowed on server to GET, HEAD and POST
    if (\$request_method !~ ^(GET|HEAD|POST)$ ) {
        return 444;
    }

    location / {
        index index.html index.htm index.php;
        try_files \$uri \$uri/ @handler;
    }

    location ^~ /app/js/ {
        allow all;
        location ~* \.(js)$ {
            #limit_conn slimits 20;
            expires 7d;
            access_log off;
        }
    }

    location ^~ /app/skin/ {
        allow all;
        location ~* \.(css|ico|gif|jpeg|jpg|png|eot|ttf|swf|woff|svg)$ {
            #limit_conn slimits 20;
            expires 30d;
            access_log off;
        }
    }

    location ^~ /app/ { deny all; }
    location ^~ /setup/ { deny all; }

    location  /. { ## Disable .htaccess and other hidden files
        return 404;
    }

    location @handler { ## A common front handler
        rewrite / /index.php;
    }

    location ~ .php$ { ## Execute PHP scripts
        if (!-e \$request_filename) { rewrite / /index.php last; } ## Catch 404s that try_files miss

        expires        off; ## Do not cache dynamic content
        fastcgi_pass   unix:{$this->_fpmListenSocket};
        fastcgi_param  SCRIPT_FILENAME  \$document_root\$fastcgi_script_name;

        include        fastcgi_params; ## See /etc/nginx/fastcgi_params
        fastcgi_read_timeout 5h;

        fastcgi_param REQUEST_URI \$sub_request_uri;
    }
}

NGINX;

        file_put_contents($nginxSiteFileName, $nginxServerDefinition);

        $this->_createSiteSymlink();

        $this->_nginxRestartRequired = true;

        $this->log('Done');
    }

    /**
     * Create symlink for devtool site.
     */
    protected function _createSiteSymlink()
    {
        if (!file_exists($this->_nginxSitesEnabledDirectory)) {
            mkdir($this->_nginxSitesEnabledDirectory);
        }
        $enabledPath = $this->_nginxSitesEnabledDirectory . $this->_nginxSiteFileName;
        if (is_link($enabledPath)) {
            $this->log('Symlink already exists and point to [' . readlink($enabledPath) . ']');
        } else {
            $this->log('Creating site symlink.');
            symlink(
                '../sites-available/' . $this->_nginxSiteFileName,
                $this->_nginxSitesEnabledDirectory . $this->_nginxSiteFileName
            );
        }
    }

    public function runChecks()
    {
        parent::runChecks();

        $toFix = array();

        $this->checkNginx($toFix);
        $this->checkNginxDefaultServer($toFix);
        $this->checkFpm($toFix);

        $this->checkSnakeOilCertificate($toFix);

        if (!is_dir('/etc/apache2')) {
            $this->log('No Apache configs found');
        } else {
            $this->checkApacheNameVirtualHost($toFix);
            $this->checkApacheListen($toFix);

            $this->checkApacheAutorun($toFix);
        }

        $toFix = array_merge($this->generateInstallCommands(), $toFix);

        if (count($toFix)) {
            $this->log("\nInstallation start...");
            $this->executeCommands($toFix);
        }
    }

    /**
     * Check nginx is installed.
     *
     * @param array $toFix
     */
    public function checkNginx(&$toFix)
    {
        $this->log('Detecting nginx binary location');
        if (`which nginx` == '') {
            $this->_missingPackages[] = 'nginx';
            $this->log(' * not found');
        } else {
            $this->log(' * ' . `nginx -v 2>&1`);
        }
    }

    /**
     * Check nginx default website.
     *
     * @param array $toFix
     */
    public function checkNginxDefaultServer(&$toFix)
    {
        $this->log('Checking nginx default server enabled');

        $defaultServerFileOld = $this->_nginxSitesEnabledDirectory . 'default';
        $defaultServerFile = $this->_nginxSitesEnabledDirectory . '000-default';

        if (file_exists($defaultServerFileOld)) {
            $toFix[] = "rm {$defaultServerFileOld}";
        }
        if (file_exists($defaultServerFile)) {
            $toFix[] = "rm {$defaultServerFile}";
        }

        if (file_exists($defaultServerFileOld) || file_exists($defaultServerFile)) {
            $this->_nginxRestartRequired = true;
            $this->log(' * default website is enabled');
        } else {
            $this->log(' * not found');
        }
    }

    /**
     * Check PHP_FPM is installed.
     *
     * @param array $toFix
     */
    public function checkFpm(&$toFix)
    {
        $phpPackagePrefix = $this->phpVersion === '5' ? 'php5' : 'php' . $this->phpVersion;
        $this->log("Detecting installed $phpPackagePrefix-fpm");
        if (trim(`dpkg -l | grep $phpPackagePrefix-fpm | awk '{ print $1 }'`) != 'ii') {
            $this->_missingPackages[] = "$phpPackagePrefix-fpm";
            $this->log(' * not found');
        } else {
            $this->log(`service $phpPackagePrefix-fpm status`);
        }
    }

    /**
     * Check Apache NameVirtualHost directive. Leave for old apache version.
     *
     * @param array $toFix
     */
    public function checkApacheNameVirtualHost(&$toFix)
    {
        $this->log('Validating Apache NameVirtualHost');
        $nameVirtualHost = getApacheNameVirtualHost();

        if ('*:80' != $nameVirtualHost) {
            $this->log(" * NameVirtualHost found {$nameVirtualHost} - OK");
            return;
        }

        $this->fixApacheHostName($toFix);
    }

    /**
     * Set IP for NameVirtualHost for old apache version if "*" is used.
     *
     * @param array $toFix
     */
    public function fixApacheHostName(&$toFix)
    {
        $line = preg_quote('NameVirtualHost *:80', '/');
        $newLine = str_replace("\n", '\n', preg_quote('NameVirtualHost 127.0.0.1:80', '/'));
        $toFix[] = "perl -pi -e 's/{$line}/{$newLine}/gi' /etc/apache2/ports.conf /etc/apache2/apache2.conf";
        $this->_apacheRestartRequired = true;
        $this->log(" * NameVirtualHost *:80 found");
    }

    /**
     * Check Apache Listen directive.
     *
     * @param array $toFix
     */
    public function checkApacheListen(&$toFix)
    {

        $this->log('Validating Apache Listen');
        $fixed = false;

        foreach (array('/etc/apache2/ports.conf', '/etc/apache2/apache2.conf') as $file) {
            if (!file_exists($file)) {
                continue;
            }
            $content = file_get_contents($file);
            if (preg_match_all('/Listen.*(80|443)/im', $content, $matches)) {
                $newLine = 'Listen 127.0.0.1:${1}';
                $content = preg_replace('/Listen.*(80|443)/im', $newLine, $content);
                file_put_contents($file, $content);
                $fixed = true;
            }
        }

        if ($fixed) {
            $this->_apacheRestartRequired = true;
            $this->log(' * Fixed!');
        } else {
            $this->log(' * Listen 80 or Listen 443 not found');
        }

    }

    /**
     * Check devtool Apache2 instance run in /etc/rc.local file.
     *
     * @param array $toFix
     */
    public function checkApacheAutorun(&$toFix)
    {
        $this->log('Looking for devtool Apache2 in rc.local');

        $file = '/etc/rc.local';
        if (!file_exists($file)) {
            $this->log(' * Not found');
            return;
        }
        $rcLocal = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (in_array($this->_apacheAutorunLine, $rcLocal) || in_array($this->_apacheAutorunLineOld, $rcLocal)) {
            $line = preg_quote($this->_apacheAutorunLine, '/');
            $toFix[] = "perl -pi -e 's/({$line})/#$1/gi' /etc/rc.local";
            $line = preg_quote($this->_apacheAutorunLineOld, '/');
            $toFix[] = "perl -pi -e 's/({$line})/#$1/gi' /etc/rc.local";

            $arg = escapeshellarg($this->_apacheAutorunLine);
            $pid = `ps aux | grep root | grep $arg | awk '{ print $2 }'`;

            if (!$pid) {
                $arg = escapeshellarg($this->_apacheAutorunLineOld);
                $pid = `ps aux | grep root | grep $arg | awk '{ print $2 }'`;
            }

            if ($pid) {
                $pid = str_replace(array("\n", "\r"), ' ', $pid);
                $toFix[] = "kill {$pid}";
            }

            $this->log(' * Found!');
        } else {
            $this->log(' * Not found');
        }
    }

    public function restartServices()
    {
        $commands = array();
        if ($this->_apacheRestartRequired) {
            $commands[] = 'service apache2 restart';
        }

        if ($this->_nginxRestartRequired) {
            $commands[] = 'service nginx restart';
        }

        if ($this->_fpmRestartRequired) {
            $commands[] = "service " . getFpmServiceName() . " restart";
        }

        $this->log('Restarting services...');
        $this->executeCommands($commands);
    }

    /**
     * Check Snake Oil SSL certificate files exist.
     *
     * @param array $toFix
     */
    public function checkSnakeOilCertificate(&$toFix)
    {
        $this->log('Checking for the snake oil SSL certificate and key:'
            . "\n\t{$this->sslCertificateFile}\n\t{$this->sslKeyFile}");

        if (file_exists($this->sslCertificateFile)
            && file_exists($this->sslKeyFile)
        ) {
            $this->log(" * {$this->sslCertificateFile} and {$this->sslKeyFile} are found");
        } else {
            $this->_missingPackages[] = 'ssl-cert';
            $toFix[] = "make-ssl-cert generate-default-snakeoil --force-overwrite";

            $this->log(' * Not found');
        }
    }

}

NginxInstaller::getInstance()->run();
