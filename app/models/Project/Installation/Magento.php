<?php

namespace Project\Installation;

class Magento
{
    /**
     * @var Magento\Module $module
     */
    public $module;

    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    public function __construct($inst)
    {
        $this->inst = $inst;
        $this->module = new Magento\Module($inst);
    }

    function flushCaches($options = array('mode' => 'all'))
    {

        $options = (object)$options;

        if (!$this->inst->isCloud) {
            // for byte hosting
            $commands = array();
            if ($options->mode === 'all' || $options->flush->byteMemcache) {
                $commands[] = 'byte-flush-caches -f memcache';
            }
            if ($options->mode === 'all' || $options->flush->byteApc) {
                $commands[] = 'byte-flush-caches -f apc';
            }
            if (count($commands)) {
                $this->inst->execOld($commands);
            }
        }

        $return = $this->inst->execRaiScriptByUrl('caches/flush.php', array('options' => json_encode($options)));

        if ($options->mode === 'all' && $this->inst->type === 'local') {
            // due to magento bug: BO form fields is not flushed
            $this->inst->execOld('sudo rm -rf var/cache/*');
        }

        return $return;

    }

    function adjustDbToDev()
    {
        $this->resetCustomAdminURL();
        $this->resetAdminSessionLifetime();
        $this->resetDevRestrictions();
        $this->resetBoPassword();
        $this->fakeEmails();
        if ($this->inst->project->type === 'magento2') {
            $this->adjustDbToDevM2();
        }
    }

    function adjustDbToDevM2()
    {
        // merge doesn't work (slow downs a mush) in developer mode, disable it
        \Mysql::query('update core_config_data set value = 0 where path in ("dev/js/merge_files", "dev/css/merge_css_files")');
        // set fake license for Wyomind Data Feed Manager, because real license removed by module and BO works very slow with empty values
        \Mysql::query("insert into core_config_data (scope,scope_id,path,value) values
            ('default', 0, 'datafeedmanager/license/activation_code', '0:2:7wxgEroUEAbHbpPezKGEvCDTTO3Pslkp:7OqgOUFY+Y+F5MOMjdwpGP7DPRPz+y9pfsnj/a6xcUw='),
            ('default', 0, 'datafeedmanager/license/activation_key', '0:2:iJue2ii3iqnlqcLnick5BeV58xrlj5Ns:S4qdugLHFdwB/Ht2mVRdp+8KzC1fifkjhNIqC6THfUk=')
            on duplicate key update core_config_data.value=VALUES(value)");
    }

    function getMaintenanceInfo()
    {

        $info = array();
        $info['standard'] = array();
        $info['standard']['status'] = $this->inst->execOld('cat index.php | grep "/\*devtool maintenance\*/"');

        if ($this->inst->execOld('cat app/etc/modules/ISMDEV_Maintance.xml') || $this->inst->execOld('cat app/etc/modules/ISMDEV_All.xml')) {
            if (preg_match('~<ISMDEV_Maintance>.+<active>true</active>~ism', $this->inst->execOutput)) {
                $info['customOld'] = array();
                if ($this->inst->execOld('cat .htaccess')) {
                    foreach (explode("\n", $this->inst->execOutput) as $line) {
                        if (stripos($line, 'ISMDEV_MAINTANCE')) {
                            $info['customOld'][] = $line;
                        }
                    }
                }
            }
        }
        if ($this->inst->execOld('cat dev/app/actions/maintenance/turnon.php')) {
            $info['customNew'] = array();
        }

        return $info;

    }

    function getLinksInfo($forceRefresh = false)
    {
        $linksInfo = \Vars::get(null, $this->inst->project->name, $this->inst->name, 'linksInfo');
        if (
            !$forceRefresh
            && $linksInfo
            && isset($linksInfo->list)
            && ($linksInfo->mainDomain === $this->inst->domain)
        ) {
            return $linksInfo;
        }
        $linksInfo = new \stdClass();
        if (($mainDomain = $this->getMainDomain()) === false) {
            return false;
        }
        // provide main domain even if getLinks does not work due to wrong inst->domain (rai by url will fail)
        $linksInfo->mainDomain = $mainDomain;
        if ($links = $this->getLinks()) {
            $linksInfo->list = $links;
            $linksInfo->date = date('Y-m-d H:i:s');
        }
        \Vars::set(null, $this->inst->project->name, $this->inst->name, 'linksInfo', $linksInfo);
        return $linksInfo;
    }

    function getLinks()
    {
        $raiScript = $this->inst->project->type === 'magento2' ? 'getLinksM2.php' : 'getLinks.php';
        return $this->inst->execRaiScriptByUrl($raiScript);
    }

    public function getBoLink()
    {
        if (
            (!$linksInfo = $this->getLinksInfo())
            || !isset($linksInfo->list)
        ) {
            return false;
        }

        foreach ($linksInfo->list as $link => $type) {
            return $link;
        }
        return false;
    }

    function getMainDomain()
    {
        return $this->inst->execRaiScript('getMainDomain.php');
    }

    function resetCustomAdminURL()
    {
        $hasBoCustomUrl = \Mysql::query('select * from core_config_data where path = "admin/url/use_custom" and value = 1')->rowCount();
        if ($hasBoCustomUrl) {
            \Mysql::query('delete from core_config_data where path = "admin/url/use_custom"');
            \Mysql::query('delete from core_config_data where path = "admin/url/custom"');
        }
    }

    function resetAdminSessionLifetime()
    {
        \Mysql::query('delete from core_config_data where path = "admin/security/session_cookie_lifetime"');
        \Mysql::query('insert into core_config_data (`scope`, `scope_id`, `path`, `value`) values ("default", "0", "admin/security/session_cookie_lifetime", "99999")');
    }

    function resetDevRestrictions()
    {
        \Mysql::query('delete from core_config_data where path = "dev/restrict/allow_ips"');
        \Mysql::query('insert into core_config_data (`scope`, `scope_id`, `path`, `value`) values ("default", "0", "dev/restrict/allow_ips", "")');
    }

    function resetBoPassword()
    {
        if (\Mysql::query('select * from admin_user where username="admin"')->rowCount() === 0) {
            \Mysql::query('update admin_user set username="admin" limit 1');
        }
        // define hash for abcABC123
        $pwdHash = '480aeb42d7b1e3937fe8db12a1ffe6d8';
        if ($this->inst->project->type === 'magento2') {
            $pwdHash = '941fbe21d93140916b0505673961fd7c3df661e2f9114f1b0f612074e5891d4a:RLSsovMhq2liFuYuoMvSYsWPqc3pn0fv:1';
        }
        \Mysql::query('
            update admin_user
            set
                password=' . \mysql::quote($pwdHash) . ',
                email="developer@company.com",
                is_active=1
            where username="ISM"
        ');
        // for EE, reset password expire date
        if ($this->getInfo()->edition === 'Enterprise' && $this->inst->project->type === 'magento1') {
            \Mysql::query('truncate table enterprise_admin_passwords;');
        }
    }

    function fakeEmails()
    {
        \Mysql::query('update core_config_data set value="fakeemailbydevtool@devnull.company.com" where value like "%@%" and (path like "%email%" or path like "%copy_to%")');
    }

    public function getModules()
    {

        $docRoot = $this->inst->_docRoot;
        $codePools = array('community', 'core', 'local');

        // find all config files
        $configFiles = array();
        foreach ($codePools as $codePool) {
            $configFiles[$codePool] = array_merge(
                glob($docRoot . "app/code/$codePool/*/etc/config.xml"),
                glob($docRoot . "app/code/$codePool/*/*/etc/config.xml"),
                glob($docRoot . "app/code/$codePool/*/*/*/etc/config.xml")
            );
            sort($configFiles[$codePool]);
        }

        // build modules array based on found config files
        $modules = array();
        foreach ($codePools as $codePool) {
            foreach ($configFiles[$codePool] as $configFile) {
                $matches = null;
                preg_match("~.*$codePool/(.*)/etc/config.xml~", $configFile, $matches);

                if ($matches[1]) {
                    $moduleName = str_replace('/', '_', $matches[1]);
                    $modules[$codePool][] = $moduleName;
                }
            }
        }

        return $modules;

    }

    public function getInfo($forceRefresh = false)
    {
        $info = $this->inst->vars->get('info');
        if (
            !$forceRefresh
            && $info
            && isset($info->edition)
            && isset($info->version)
            && isset($info->patches)
        ) {
            return $info;
        }

        $info = $this->inst->spf('mage/getInfo');

        $this->inst->vars->set('info', $info);
        return $info;
    }

    function getIncompatibleModuleNamespaces($configXmlFile)
    {
        $content = file_get_contents($this->inst->folder . $configXmlFile);
        if (!$configXml = @simplexml_load_string($content)) {
            return array("Can't check, invalid XML in $configXmlFile");
        }

        if (!$moduleName = $this->getModuleNameByConfigFile($configXmlFile)) {
            return array();
        }

        $result = array();
        foreach ($this->getModuleNamespaces($configXml) as $nodeName) {
            if (strpos(strtolower($nodeName), strtolower($moduleName)) === false) {
                $result[] = "<{$nodeName}> should contain '" . strtolower($moduleName) . "' in $configXmlFile";
            }
        }

        return $result;
    }

    function getModuleNameByConfigFile($configFile)
    {
        // get module name (excl. package name)
        $path = explode('/', $configFile);
        if (count($path) < 3) {
            // it's not module config
            return false;
        }
        return $path[count($path) - 3];
    }

    function getModuleNamespaces($configXml)
    {
        $result = array();

        $namespacesPaths = array(
            'global/layout/updates',
            'global/events/*/observers',
            'frontend/layout/updates',
            'frontend/events/*/observers',
            'adminhtml/layout/updates',
            'adminhtml/events/*/observers',
            'crontab/jobs',
        );

        foreach ($namespacesPaths as $path) {
            if ($nodes = $configXml->xpath($path)) {
                foreach ($nodes as $node) {
                    foreach ($node->children() as $child) {
                        $result[] = $child->getName();
                    }
                }
            }
        }

        return $result;
    }

    function getProblems()
    {
        return $this->inst->execRaiScriptByUrl('sanityCheck/getProblems.php');
    }

    function getConfigDump()
    {
        return $this->inst->spf('m2/getConfigDump');
    }

}
