<?php

namespace Project;

/**
 * Class Installation
 * @package Project
 *
 * @property string $type
 * @property \stdClass $project
 * @property string $name
 * @property string $folder
 * @property string $domain
 * @property string $_docRoot
 * @property string $_appRoot
 */
class Installation
{

    use Installation\Console;
    use Installation\Db;
    use Installation\Installer;
    use Installation\Installer\Helper;
    use Installation\Installer\Fpm;
    use Installation\Installer\Nginx;
    use Installation\Installer\Docker;
    use Installation\Rai;

    /**
     * @var bool $isCloud
     * @var Installation\Spf $spf
     * @var Installation\Git $git
     * @var Installation\Composer $composer
     * @var Installation\Vars $vars
     * @var Installation\Log $log
     * @var Installation\Magento $magento
     * @var Installation\Rsync $rsync
     * @var Installation\Deployment $deployment
     * @var Installation\Config $config
     * @var Installation\Maintenance $maintenance
     * @var Installation\WebServer $webServer
     * @var Installation\Dump $dump
     * @var Installation\Newrelic $newrelic
     * @var Installation\Generation $generation
     */
    public $isCloud;
    protected $spf;
    public $git;
    public $composer;
    public $vars;
    public $log;
    public $magento;
    public $rsync;
    public $deployment;
    public $config;
    public $maintenance;
    public $webServer;
    public $dump;
    public $newrelic;
    public $generation;

    public function __construct($data)
    {

        $this->vars = new Installation\Vars($this);
        $this->origData = object_clone_recursive($data);

        $data = $this->applyVariables($data);

        foreach ($data as $k => $v) {
            $this->$k = $v;
        }

        $this->hasSshAccess = isset($this->hasSshAccess) ? $this->hasSshAccess : true;
        if (!$this->hasSshAccess) {
            $this->login = $this->host = $this->folder = $this->_docRoot = $this->_appRoot = 'unknown';
        }

        // data adjusting
        $this->folder = preg_replace('~/+~', '/', "/{$this->folder}/");
        $this->_docRoot = $this->folder;
        if (isset($this->project->type) && $this->project->type !== 'simple') {
            if ($this->project->repository->docRoot !== '') {
                $this->project->repository->docRoot = trim($this->project->repository->docRoot, '/') . '/';
            }
            $this->_docRoot = $this->folder . $this->project->repository->docRoot;
        }
        $this->_appRoot = $this->_docRoot;
        if ($this->project->type === 'magento2') {
            $appRoot = '';
            if (isset($this->project->repository->appRoot)) {
                $appRoot = trim($this->project->repository->appRoot, '/');
            }
            if ($appRoot !== '') {
                $this->_appRoot = $this->folder . $appRoot . '/';
            } else {
                $this->_appRoot = $this->folder;
            }
        }

        if (isset($this->domain)) {
            $customDomain = $this->vars->get('customMainDomain');
            if (!empty($customDomain)) {
                $this->domain = $customDomain;
            }
            $domainName = trim(preg_replace('~https?:~', '', $this->domain), '/');
            $protocol = preg_match('~^https://~', $this->domain) ? 'https' : 'http';
            $this->domain = $domainName;
            $this->_url = $protocol . '://' . $domainName . '/';
        }

        $this->isCloud = isset($this->cloud);
        $this->spf = new Installation\Spf($this);
        $this->git = new Installation\Git($this);
        $this->composer = new Installation\Composer($this);
        $this->log = new Installation\Log($this);
        $this->magento = new Installation\Magento($this);
        $this->rsync = new Installation\Rsync($this);
        $this->deployment = new Installation\Deployment($this);
        $this->config = new Installation\Config($this);
        $this->maintenance = new Installation\Maintenance($this);
        $this->dump = new Installation\Dump($this);
        $this->newrelic = new Installation\Newrelic($this);
        $this->webServer = $this->createWebServerInstance();
        $this->generation = new Installation\Generation($this);

    }

    protected function createWebServerInstance()
    {
        $webServerType = $this->getWebServerType();
        if ($webServerType == 'apache') {
            $webServer = new Installation\WebServer\Apache($this);
        } elseif ($webServerType == 'nginx-php-fpm') {
            $webServer = new Installation\WebServer\Nginx($this);
        } elseif ($webServerType == 'docker') {
            $webServer = new Installation\WebServer\Docker($this);
        } else {
            $webServer = new Installation\WebServer();
        }
        return $webServer;
    }

    // use webServer->type instead
    protected function getWebServerType()
    {
        return \Vars::get(null, $this->project->name, $this->name, 'serverType');
    }

    public function setWebServerType($value)
    {
        \Vars::set(null, $this->project->name, $this->name, 'serverType', $value);
    }

    /*
     * No trait methods
     */

    function getMyIP()
    {
        return $this->execRaiScriptByUrl('info/ip.php');
    }

    public function hasStores()
    {
        return isset($this->stores) && $this->stores;
    }

    /**
     * Get stores.
     *
     * @return array
     */
    public function getStores()
    {
        return isset($this->stores) && $this->stores ? $this->stores : array();
    }

    /**
     * @param $code
     * @return \stdClass|null
     */
    public function getStoreByCode($code)
    {
        if (!$this->hasStores()) {
            return null;
        }

        if (empty($this->storesByCode)) {
            $keys = array();
            foreach ($stores = $this->getStores() as $store) {
                $keys[] = $store->mageRunCode;
            }
            $this->storesByCode = array_combine($keys, (array)$stores);
        }

        return isset($this->storesByCode[$code]) ? $this->storesByCode[$code] : null;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        $instKey = strtolower(preg_replace('~[^0-9a-z]~i',
            '',
            $this->project->name . $this->name));
        return $instKey;
    }

    /**
     * @return \Project\Installation
     */
    public function inst()
    {
        return $this;
    }

    /**
     * @deprecated
     */
    public function getDataJson()
    {
        return json_encode($this->getInfo());
    }

    public function getInfo()
    {
        return (object)[
            'name' => $this->name,
            'source' => $this->source,
            'type' => $this->type,
            'folder' => $this->folder,
            'host' => isset($this->host) ? $this->host : null,
            'project' => (object)[
                'name' => $this->project->name,
                'type' => $this->project->type,
            ],
            'webServer' => (object)[
                'type' => $this->webServer->type
            ],
            'isCloud' => $this->isCloud
        ];
    }

    public function spf()
    {
        $args = func_get_args();
        $this->spf->function = array_shift($args);
        $this->spf->args = $args;
        return $this->spf->run();
    }

    public function form($form, $vars = array())
    {
        $found = false;
        $checkedTemplates = [];
        $search = $this->isCloud ? ['magento2.cloud'] : [];
        $search = array_merge($search, [$this->project->type, 'default']);
        foreach ($search as $projectType) {
            $formTemplate = "project/installation/forms/$projectType/$form";
            if (file_exists(TPL_DIR . $formTemplate . '.phtml')) {
                $found = true;
                break;
            }
            $checkedTemplates[] = $formTemplate;
        }
        if (!$found) {
            error(
                'Form template "' . $form . "\" not found in the following locations:\n"
                . var_export($checkedTemplates, true)
            );
        }
        if ($vars instanceof \stdClass) {
            $vars = (array)$vars;
        }
        $vars['inst'] = $this;
        return template($formTemplate, $vars);
    }

    static function getVariables()
    {

        $vars['workspace'] = \Config::getData()->workspace;
        $vars['user'] = USER;

        return $vars;

    }

    protected function applyVariables($data)
    {

        $vars = $this->getVariables();
        $varValues = array_values($vars);
        $varKeys = array_keys($vars);
        array_walk($varKeys, function (&$value) {
            $value = '{' . $value . '}';
        });

        object_walk_recursive($data, function (&$value) use ($varKeys, $varValues) {
            $value = str_replace($varKeys, $varValues, $value);
        });

        return $data;

    }

    function getBashConnectionString()
    {
        $portExpr = !empty($this->port) ? "-p{$this->port} " : "";
        return "ssh -t $portExpr" . $this->getConnectionStringLogin() . "@$this->host 'cd $this->_appRoot; bash'\n";
    }

    function getMcConnectionString()
    {
        $port = !empty($this->port) ? $this->port : "";
        return $this->getConnectionStringLogin() . "@$this->host:{$port}$this->_appRoot";
    }

    function getConnectionStringLogin()
    {
        return isset($this->origData->login) && $this->origData->login === "{user}" ? LDAP_USER : $this->login;
    }

    function getCdString()
    {
        return "cd $this->_appRoot\n";
    }

    function getLocalInstallation()
    {
        return \Project::getLocalInstallation($this->source, $this->project->name);
    }

}
