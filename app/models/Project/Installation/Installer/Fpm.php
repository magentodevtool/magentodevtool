<?php

namespace Project\Installation\Installer;

trait Fpm
{
    function checkPhpFpmInstalled()
    {
        return isPhpFpmInstalled();
    }

    /**
     * Checks common Magento config files exist for PHP-FPM servers.
     *
     * @return bool
     */
    function checkFpmMagentoFiles()
    {
        return file_exists('/etc/php5/fpm/pool.d/magento.conf')
            && $this->checkFpmMagentoFilesUptodate();
    }

    /**
     * Checks common Magento config files exist and are up to date for PHP-FPM servers.
     *
     * @return bool
     */
    function checkFpmMagentoFilesUptodate()
    {
        $sampleConfig = PATH_CONFIG_FPM;

        return (filemtime($sampleConfig . '/pool.d/magento.conf') <= filemtime('/etc/php5/fpm/pool.d/magento.conf'));
    }

    /**
     * Copy common Magento config files from skeleton overwriting outdated existing files.
     *
     * @return bool
     */
    function fixFpmMagentoFiles()
    {
        $sampleConfig = PATH_CONFIG_FPM;

        exec(cmd('sudo cp -u %s /etc/php5/fpm/pool.d/', $sampleConfig . '/pool.d/magento.conf'));

        // postpone PHP-FPM reload
        register_shutdown_function(array($this, 'reloadFpm'));

        return $this->checkFpmMagentoFiles();
    }

    function reloadFpm()
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            sleep(3);
            reloadFpm();
        }
    }

}
