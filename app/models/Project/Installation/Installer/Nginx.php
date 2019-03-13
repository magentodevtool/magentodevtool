<?php

namespace Project\Installation\Installer;

/**
 * Class Nginx
 * @package Project\Installation
 *
 * @method \Project\Installation inst()
 *
 */
trait Nginx
{
    function checkNginxInstalled()
    {
        return isNginxInstalled();
    }

    function checkNginxIsRunning()
    {
        return count(getServiceListening('nginx')) > 0;
    }

    /**
     * Check enabled nginx server is configured.
     *
     * @return bool
     */
    function checkNginxServer()
    {
        // check nginx is installed
        if (!isNginxInstalled()) {
            return false;
        }

        $config = $this->inst()->webServer->getConfig();

        return $config && in_array($this->inst()->domain, $config->domains);
    }

    /**
     * Fix nginx server definition either by copying or generating.
     *
     * @return bool
     */
    function fixNginxServer()
    {
        // cannot really do anything with nginx
        if (!($this->inst()->webServer->type)) {
            return false;
        }

        // if magento is placed right in repo root
        if ($this->inst()->folder !== $this->inst()->_docRoot) {
            $logsDir = $this->inst()->folder . 'logs/';
            if (!is_dir($logsDir)) {
                umask(0);
                mkdir($logsDir, 0777);
            }
        }
        // find files in the config directory
        if (!$this->copyNginxServer($this)) {
            // generate if not found
            $this->installNginxServer();
        }

        return $this->checkNginxServer();
    }

    /**
     * @return bool
     */
    function copyNginxServer()
    {
        return false;

        // locate configuration files in the project directory
        $configDirectory = $this->inst()->folder . '/configs/dev/linux/nginx/sites-available/';
        $configFileName = strtolower(preg_replace('~[^0-9A-z]~', '', $this->inst()->domain)) . 'conf';

        if (!is_dir($configDirectory) || !file_exists($configDirectory . $configFileName)) {
            return false;
        }

        $virtualHost = file_get_contents($configDirectory . $configFileName);

        // TODO: replace values with local from Installation config;

        $configPath = '/etc/nginx/sites-available/' . $configFileName;
        $configEnabledPath = '/etc/nginx/sites-enabled/' . $configFileName;

        sudo_file_put_contents($configPath, $virtualHost);

        exec(cmd('sudo ln -s %s %s', '../sites-available/' . $configFileName, $configEnabledPath));

        return false;
    }

    /**
     * Generate nginx server configuration.
     *
     * @return bool
     */
    function installNginxServer()
    {
        $instKey = $this->inst()->getKey();

        if (!($virtualHost = $this->generateNginxServer())) {
            return false;
        }

        $configFileName = $instKey . '.local.conf';
        $configPath = '/etc/nginx/sites-available/' . $configFileName;
        $configEnabledPath = '/etc/nginx/sites-enabled/' . $configFileName;

        sudo_file_put_contents($configPath, $virtualHost);
        exec(cmd('sudo ln -s %s %s', '../sites-available/' . $configFileName, $configEnabledPath));

        // create logs dir
        if ($this->inst()->folder !== $this->inst()->_docRoot) {
            $logsDir = $this->inst()->folder . 'logs/';
            if (!is_dir($logsDir)) {
                $umask = umask(0);
                mkdir($logsDir, 0777);
                umask($umask);
            }
        }

        reloadNginx();

        return $this->checkNginxServer();
    }

    /**
     * @return string
     */
    function generateNginxServer()
    {
        if (!($this->inst()->webServer->type)) {
            return false;
        }

        if ($this->inst()->folder === $this->inst()->_docRoot) {
            $instKey = $this->inst()->getKey();
            $logAccessFile = "access.$instKey.log";
            $logErrorFile = "error.$instKey.log";
        } else {
            $logsDir = $this->inst()->folder . 'logs/';
            $logAccessFile = $logsDir . 'access.log';
            $logErrorFile = $logsDir . 'error.log';
        }

        $this->inst()->_logAccessFile = $logAccessFile;
        $this->inst()->_logErrorFile = $logErrorFile;

        switch ($this->inst()->webServer->type) {
            case 'nginx-php-fpm':
                $virtualHost = $this->generateNginxServerPhpFpm();
                break;
            case 'apache':
            default:
                // should not do anything here
                return false;
        }

        return $virtualHost;
    }

    /**
     * @return mixed|string
     */
    function generateNginxServerPhpFpm()
    {
        $replacements = $this->getNginxReplacements();

        if ($this->inst()->folder !== $this->inst()->_docRoot) {
            $manualTests = <<<'NGINX'
    location ^~ /manual-tests/ {
        root __project_root__/tests/;

        location ~ /manual-tests(/.*\.php)$ {
            if (!-e $request_filename) { rewrite / ../../src/error/404.php last; } ## Catch 404s that try_files miss

            fastcgi_pass unix:/var/run/php5-fpm-magento.sock;

            fastcgi_param SCRIPT_FILENAME __project_root__/tests/manual-tests/$1;
            include fastcgi_params;
            fastcgi_read_timeout 5h;

            # prepend XHProf header if xhprof cookie is set for profiling.
            if ($cookie_xhprof) {
                set $php_value "auto_prepend_file=/usr/local/share/php5/utilities/xhprof/header.php";
            }

            fastcgi_param PHP_VALUE $php_value;
        }
    }
NGINX;
        } else {
            $manualTests = '';
        }

        $virtualHostTemplate = <<<'NGINX'
server {
    listen __listen_ip__:80;
    listen __listen_ip__:443 ssl;

    server_name __server_name__;

    root __doc_root__;

    ssl_certificate /etc/ssl/certs/ssl-cert-snakeoil.pem;
    ssl_certificate_key /etc/ssl/private/ssl-cert-snakeoil.key;

    access_log __access_log__;
    error_log __error_log__;

    # Accept connections only for domains, located on this server, and send 444 if request address is different
    # list domains here
    if ($host !~ ^(__server_name_pipe__)$ ) {
        return 444;
    }

    include /etc/nginx/magento/magento.conf;

    #__manual_tests__

    set $mage_run_type store; # store|website
    set $mage_run_code __mage_run_code__;

    # This includes global php configuration
    # It has to be included to each php location
    include /etc/nginx/magento/php5-fcgi-magento.conf;
}

NGINX;

        $virtualHost = str_replace('#__manual_tests__', $manualTests, $virtualHostTemplate);
        $virtualHost = str_replace(array_keys($replacements), array_values($replacements), $virtualHost);

        if ($this->inst()->hasStores()) {
            foreach ($this->inst()->getStores() as $store) {
                $replacements['__server_name__'] = $store->storeDomain;
                $replacements['__mage_run_code__'] = $store->mageRunCode;

                $virtualHost .= PHP_EOL . str_replace(
                        array_keys($replacements),
                        array_values($replacements),
                        $virtualHostTemplate);
            }
        }

        return $virtualHost;
    }

    /**
     * @return array
     */
    function getNginxReplacements()
    {
        $installation = $this->inst();

        $replacements = array(
            '__listen_ip__' => getServiceLocalIp('nginx'),
            '__project_root__' => $installation->folder,
            '__server_name__' => $installation->domain,
            '__doc_root__' => $installation->_docRoot,
            '__mage_run_code__' => isset($installation->mageRunCode) ? $installation->mageRunCode : 'default',
            '__access_log__' => $installation->_logAccessFile,
            '__error_log__' => $installation->_logErrorFile
        );

        if (isset($installation->stores) && $installation->stores) {
            foreach ($installation->stores as $store) {
                $replacements['__server_name__'] .= ' ' . $store->storeDomain;
            }
        }

        $replacements['__server_name_pipe__'] = str_replace(' ', '|', $replacements['__server_name__']);

        return $replacements;
    }

    /**
     * Checks common Magento config files exist for PHP-FPM servers.
     *
     * @return bool
     */
    function checkNginxMagentoFiles()
    {
        return file_exists('/etc/nginx/magento/magento.conf')
            && file_exists('/etc/nginx/magento/php5-fcgi-magento.conf')
            && file_exists('/etc/nginx/conf.d/security.conf')
            && $this->checkNginxMagentoFilesUptodate();
    }

    /**
     * Checks common Magento config files exist and are up to date for PHP-FPM servers.
     *
     * @return bool
     */
    function checkNginxMagentoFilesUptodate()
    {
        $sampleConfig = PATH_CONFIG_NGINX;

        return (filemtime($sampleConfig . '/magento/magento.conf') <= filemtime('/etc/nginx/magento/magento.conf'))
            && (filemtime($sampleConfig . '/conf.d/security.conf') <= filemtime('/etc/nginx/conf.d/security.conf'))
            && (
                filemtime($sampleConfig . '/magento/php5-fcgi-magento.conf')
                <= filemtime('/etc/nginx/magento/php5-fcgi-magento.conf')
            );
    }

    /**
     * Copy common Magento config files from skeleton overwriting outdated existing files.
     *
     * @return bool
     */
    function fixNginxMagentoFiles()
    {
        $sampleConfig = PATH_CONFIG_NGINX;

        if (!is_dir('/etc/nginx/magento')) {
            sudo_mkdir('/etc/nginx/magento');
        }

        exec(cmd('sudo cp -u %s /etc/nginx/magento/', $sampleConfig . '/magento/magento.conf'));
        exec(cmd('sudo cp -u %s /etc/nginx/magento/', $sampleConfig . '/magento/php5-fcgi-magento.conf'));
        exec(cmd('sudo cp -u %s /etc/nginx/conf.d/', $sampleConfig . '/conf.d/security.conf'));

        reloadNginx();

        return $this->checkNginxMagentoFiles();
    }

}
