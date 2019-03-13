<?php

namespace Project\Installation;

/**
 * Class Db
 * @package Project\Installation
 *
 * @method \Project\Installation inst()
 */
trait Db
{

    function findInstallationsByDbName($dbName, $returnFirst = true, $excludeCurrent = true)
    {
        $installations = array();
        foreach (array('local', 'remote') as $source) {
            foreach (\Projects::getList($source) as $projectName => $project) {
                if ($project->type === 'simple') {
                    continue;
                }
                foreach ($project->installations as $installationName => $installation) {
                    if ($installation->type !== "local") {
                        continue;
                    }
                    $inst = \Projects::getInstallation($source, $projectName, $installationName);
                    if (!is_dir($inst->_appRoot)) {
                        // otherwise getDbCredentials SPF will fail
                        continue;
                    }
                    if ($inst->webServer->type === 'docker') {
                        continue;
                    }
                    if ($excludeCurrent && ($this->_docRoot === $inst->_docRoot)) {
                        // skip current installation
                        continue;
                    }
                    $dbCredentials = $inst->getDbCredentials('app');
                    if ($dbCredentials->dbname === $dbName) {
                        $installations[] = array(
                            'project' => $projectName,
                            'name' => $installationName,
                        );
                        if ($returnFirst) {
                            return $installations[0];
                        }
                    }
                }
            }
        }
        return $installations;
    }

    function getDbXmlFile()
    {

        $dbFile = false;
        foreach (array('local.database.xml', 'local.local.xml', 'local.xml') as $file) {
            $file = $this->inst()->_docRoot . 'app/etc/' . $file;
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (strpos($content, '<dbname>') && ($xml = @simplexml_load_string($content))) {
                    $dbFile = $file;
                }
            }
        }

        return $dbFile;

    }

    /**
     * @param string $type
     */
    function getDbCredentials($type = 'external')
    {
        if (!in_array($type, ['app', 'external'])) {
            error('Invalid type passed to the ' . __METHOD__);
        }

        if ($type === 'external') {
            return $this->getExternalDbCredentials();
        } else {
            return $this->getAppDbCredentials();
        }

    }

    /**
     * get db credentials from application configuration
     */
    protected function getAppDbCredentials()
    {
        return $this->inst()->spf('db/getAppCredentials');
    }

    /**
     * get db credentials to connect outside application (devtool and developer)
     */
    protected function getExternalDbCredentials()
    {
        if ($this->inst()->webServer->type === 'docker') {
            return $this->getDockerExternalMysqlServerCredentials();
        }

        // external and app credentials are the same for non-docker installation
        return $this->getAppDbCredentials();
    }

    /**
     * get valid MySQL server credentials which is possible to use by app
     */
    function getDbCredentialsForApp()
    {
        if ($this->inst()->webServer->type !== 'docker') {
            error('Method ' . __METHOD__ . ' is implemented for Docker only');
        }

        $cred = $this->getDockerExternalMysqlServerCredentials();
        $cred->host = 'database';
        return $cred;
    }

    /**
     * get valid Docker external MySQL server credentials which is possible to use externally (by devtool or developer)
     */
    protected function getDockerExternalMysqlServerCredentials()
    {
        if ($this->inst()->webServer->type !== 'docker') {
            error('Method ' . __METHOD__ . ' is implemented for Docker only');
        }

        $extCred = (array)$this->inst()->spf('docker/getExternalMysqlServerCredentials');

        // merge to add database name
        $appCred = (array)$this->getAppDbCredentials();
        return (object)array_merge($appCred, $extCred);
    }

    function setDbCredentials($newCred)
    {
        if ($this->project->type === 'magento2') {
            return $this->setDbCredentialsMagento2($newCred);
        } else {
            return $this->setDbCredentialsMagento1($newCred);
        }
    }

    protected function setDbCredentialsMagento1($newCred)
    {
        $dbFile = $this->getDbXmlFile();
        $xml = simplexml_load_file($dbFile);

        $res = $xml->xpath('global/resources/default_setup/connection');
        $dbNode = $res[0];

        foreach ($newCred as $k => $v) {
            $dbNode->$k = $v;
        }

        return $xml->asXML($dbFile);
    }

    protected function setDbCredentialsMagento2($newCred)
    {
        $envFile = $this->_appRoot . 'app/etc/env.php';
        $config = include $envFile;
        $cred = &$config['db']['connection']['default'];
        foreach ($newCred as $k => $v) {
            $cred[$k] = $v;
        }
        if (isset($cred['port'])) {
            $mageInfo = $this->inst()->spf('mage/getInfo');
            if ($mageInfo->version >= '2.2') {
                $cred['host'] = $cred['port'] == 0 ? $cred['host'] : $cred['host'] . ':' . $cred['port'];
                unset($cred['port']);
            }
        }
        $newEnvFileContent = "<?php\nreturn " . var_export($config, true) . ';';
        return file_put_contents($envFile, $newEnvFileContent);
    }

    function getDbDumps()
    {

        $appRoot = $this->_appRoot;
        $dumps = array_merge(
            glob($appRoot . 'var/*.sql'),
            glob($appRoot . 'var/*.sql.gz'),
            glob($appRoot . 'var/*/*.sql'),
            glob($appRoot . 'var/*/*.sql.gz')
        );

        // make relative path instead of absolute
        foreach ($dumps as &$dump) {
            $dump = preg_replace('~^' . preg_quote($appRoot . 'var/') . '~', '', $dump);
        }

        return $dumps;
    }

    function getBaseURLDomainsToReplace()
    {

        $baseUrlDomains = $this->getBaseURLDomains();
        $domainsToReplace = array();

        $domains = $this->inst()->webServer->getDomains();
        foreach ($baseUrlDomains as $domain) {
            if (!in_array($domain, $domains)) {
                $domainsToReplace[] = $domain;
            }
        }

        return $domainsToReplace;

    }

    function getAdditionalApacheAliases()
    {
        // return local domains from database which aren't registered in virtual host
        $baseURLDomains = $this->getBaseURLDomains();
        $virtualHostDomains = $this->inst()->webServer->getDomains();
        $aliases = array_diff($baseURLDomains, $virtualHostDomains);
        foreach ($aliases as $key => $domain) {
            if ($this->inst()->isDomainLocal($domain)) {
                continue;
            }
            unset($aliases[$key]);
        }
        return $aliases;
    }

    function getBaseURLDomains()
    {
        $baseUrlDomains = array();
        $sql = 'select * from core_config_data where path like "web/%secure%url" and value like "http%"';
        foreach (\Mysql::query($sql)->fetchAll() as $row) {
            if (preg_match('~^https?://([^/]+)/~', $row['value'], $ms)) {
                $baseUrlDomains[] = $ms[1];
            }
        }
        $baseUrlDomains = array_unique($baseUrlDomains);
        return $baseUrlDomains;
    }

    function getBaseURLsSSL()
    {
        $urls = array();

        if ($res = \Mysql::query(
            'select value from core_config_data where path like "web/%secure/base%url" and value like "https%"'
        )
        ) {
            while ($value = $res->fetchColumn()) {
                $urls[] = $value;
            }
        }

        return $urls;
    }

    function getDatabases()
    {

        if (!\Mysql::server($this->getDbCredentials())) {
            return array();
        }

        $databases = \Mysql::query('show databases')->fetchAll(\PDO::FETCH_COLUMN);

        $filter = $this->getDbSimpleFilter();
        foreach ($databases as $k => $v) {
            if (!simpleFilterTest($filter, $v)) {
                unset($databases[$k]);
            }
        }

        return $databases;

    }

    function getDbSimpleFilter()
    {
        $inst = $this->inst();
        return ($dbSimpleFilter = $inst->vars->get('dbSimpleFilter')) ? $dbSimpleFilter : $this->getDefaultDbSimpleFilter();
    }

    function getDefaultDbSimpleFilter()
    {
        $filter = preg_replace(
                ['~^https?://~', '~\..+$~', '~[^\w]~'],
                ['', '', '*'],
                $this->inst()->_url
            )
            . '*';
        $dbname = $this->getDbCredentials()->dbname;
        if (!simpleFilterTest($filter, $dbname)) {
            $filter .= ',' . $dbname;
        }
        return $filter;
    }

    function getDefaultDbName()
    {
        $name = preg_replace(array('~^https?://~', '~\..+$~', '~[^\w]~'), array('', '', '_'), $this->inst()->_url);
        $name = strtolower($name) . '_' . date('Y_m_d');
        $name = preg_replace(array('~[^a-z0-9:]~i', '~_+~'), '_', $name);
        return $name;
    }

    function getDbBackups()
    {

        $output = $this->inst()->exec(array(
            'cd var',
            "find . -maxdepth 2 -follow -regextype posix-egrep -regex '.*\\.(sql|sql\\.gz)\$' -not -path './cache/*' -not -path './report/*' -not -path './session/*' -exec ls -lh --time-style=\"+%Y-%m-%d %H:%M:%S %z\" {} \\;"
        ));

        $backups = array();
        foreach (explode("\n", $output) as $line) {
            if (preg_match('~^[^\s]+ [^\s]+ [^\s]+ [^\s]+ ([^\s]+) ([^\s]+ [^\s]+ [^\s]+) (.+)$~', $line, $ms)) {
                $backups [] = array(
                    'size' => $ms[1],
                    'date' => $ms[2],
                    'file' => preg_replace('~^\./~', '', $ms[3]),
                );
            }
        }

        // sort by date
        uasort($backups, function ($a, $b) {
            if ($a['date'] == $b['date']) {
                return 0;
            }
            return $a['date'] > $b['date'] ? 1 : -1;
        });


        return $backups;

    }

    function getDefaultBackupName()
    {
        $name = strtolower($this->inst()->project->name . '_' . $this->inst()->name) . '-' . date('Y-m-d');
        $name = preg_replace(array('~[^a-z0-9:]~i', '~-+~'), '-', $name);
        return $name;
    }

    function getForeignKeysTotals($foreignKeys)
    {
        $toUpdate = 0;
        $toDelete = 0;
        foreach ($foreignKeys as $columns) {
            foreach ($columns as $keys) {
                foreach ($keys as $key) {
                    $toUpdate += $key->to_update;
                    $toDelete += $key->to_delete;
                }
            }
        }
        return compact('toUpdate', 'toDelete');
    }

    function getDbDumpVersion($dumpFile)
    {
        $f = fopen($dumpFile, 'r');
        $linesLimit = 5000;
        $lineN = 0;
        while ($line = fgets($f)) {
            if (strpos($line, '-- MySQL dump ') === 0) {
                break;
            }
            $lineN++;
            if ($lineN >= $linesLimit) {
                break;
            }
        }
        fclose($f);
        if (!$line) {
            \error('Failed to get first dump line');
        }
        return $this->parseMysqlVersion($line);
    }

    function getMysqlVersion()
    {
        if ($this->webServer->type === 'docker') {
            $text = $this->execInDockerService('database', '', 'mysql --version');
        } else {
            $text = $this->exec('mysql --version');
        }
        return $this->parseMysqlVersion($text);
    }

    protected function parseMysqlVersion($text)
    {
        if (!preg_match('~Distrib ([0-9]+\.[0-9]+)~', $text, $matches)) {
            \error('Failed to parse MySQL version from text');
        }
        return $matches[1];
    }

    function getInitialStatementsBeforeDumpImport($dumpFile)
    {
        $statements = [];
        $localMysqlVersion = $this->getMysqlVersion();
        $dumpMysqlVersion = $this->getDbDumpVersion($dumpFile);
        if ($dumpMysqlVersion === '5.6' && $localMysqlVersion === '5.7') {
            $statements[] = 'set @@global.show_compatibility_56 = ON';
        }
        return $statements;
    }

    function getInitCommandBeforeDumpImport($dumpFile)
    {
        $initialStatements = $this->getInitialStatementsBeforeDumpImport($dumpFile);
        if (count($initialStatements)) {
            $initCommand = shellescapef(
                '--init-command=%s',
                implode(';', $initialStatements)
            );
        } else {
            $initCommand = '';
        }
        return $initCommand;
    }

}
