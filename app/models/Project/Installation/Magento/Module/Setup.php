<?php

namespace Project\Installation\Magento\Module;

class Setup
{
    /** @var \Project\Installation $inst */
    protected $inst;
    protected $modulesInst;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    /**
     * @return bool|\Project\Installation
     */
    function getModulesInstallation()
    {
        if ($this->modulesInst) {
            return $this->modulesInst;
        }
        if (!$params = \Config::getNode('magento/modulesInstallation')) {
            return false;
        }
        if (!isset($params->source) || !isset($params->project) || !isset($params->name)) {
            return false;
        }
        $this->modulesInst = \Projects::getInstallation($params->source, $params->project, $params->name);
        return $this->modulesInst;
    }

    function validateModulesInstallation()
    {
        if (!$this->getModulesInstallation()) {
            error("Modules installation not found. Please check config.json->magento->modulesInstallation.");
        }

        $modulesInst = $this->getModulesInstallation();

        if (!is_dir($modulesInst->folder) || (count(scandir($modulesInst->folder)) === 2)) {
            $e = new \Exception("Modules installation isn't cloned");
            $e->key = 'notCloned';
            throw $e;
        }

        if (!is_dir($modulesInst->folder . '.git')) {
            error("Modules installation isn't a Git repository");
        }

        $fullName = $modulesInst->project->name . ' / ' . $modulesInst->name;
        $currentBranch = $modulesInst->git->getCurrentBranch();

        if ($currentBranch !== 'master') {
            error("$fullName must be on 'master' branch");
        }

        if (count($modulesInst->git->getModifications()->diff)) {
            error("$fullName must be clean, modifications detected");
        }

        $modulesInst->git->fetch();

        if ($ahead = count($modulesInst->git->getBranchAheadCommits($currentBranch))) {
            error("$fullName isn't up to date with origin: ahead for $ahead commits");
        }

        if ($behind = count($modulesInst->git->getBranchBehindCommits($currentBranch))) {
            $e = new \Exception("$fullName isn't up to date with origin: behind for $behind commits");
            $e->key = 'behind';
            throw $e;
        }
    }

    function getAvailableModules()
    {
        $modulesInst = $this->getModulesInstallation();
        $scanFolder = isset($modulesInst->_docRoot) ? $modulesInst->_docRoot : $modulesInst->folder;
        $configFiles = $this->getModulesInstallation()->spf('listdir', $scanFolder, true, true, '~etc/config\.xml$~');
        $modules = array();
        foreach ($configFiles as $configFile) {
            if (!preg_match('~/app/code/([^/]+)/(([^/]+/){2,3})etc/config\.xml$~', $configFile, $ms)) {
                continue;
            }
            $moduleName = str_replace('/', '_', trim($ms[2], '/'));
            $packageName = preg_replace('~_[^_]+$~', '', $moduleName);

            $module = (object)array(
                'name' => $moduleName,
                'codePool' => $ms[1],
                'folder' => str_replace(trim($ms[0], '/'), '', $configFile),
                'package' => $packageName,
                'isInstalled' => false,
            );

            $module->valid = true;
            $module->error = null;
            try {
                $this->validateModule($module);
            } catch (\Exception $e) {
                $module->valid = false;
                $module->error = $e->getMessage();
            }

            $module->depends = $this->getModuleDepends($module);

            $modules[$moduleName] = $module;
        }

        uasort($modules, function ($a, $b) {
            return $a->name > $b->name;
        });

        $this->addIsInstalledFlag($modules);

        return $modules;
    }

    protected function addIsInstalledFlag(&$modules)
    {
        $projectModules = $this->inst->magento->getModules();
        foreach ($projectModules as $codePool => $codePoolModules) {
            foreach ($codePoolModules as $moduleName) {
                if (isset($modules[$moduleName])) {
                    $modules[$moduleName]->isInstalled = true;
                }
            }
        }
    }

    protected function getModuleDepends($module)
    {
        $depends = array();

        $etcXmlFiles = listdir($module->folder . 'app/etc/modules', true);
        if (!count($etcXmlFiles)) {
            return $depends;
        }

        $etcXmlFile = $etcXmlFiles[0];
        $etcXml = @simplexml_load_file($etcXmlFile);
        if (!$etcXml) {
            return $depends;
        }

        if (!isset($etcXml->modules->{$module->name}->depends)) {
            return $depends;
        }

        foreach ($etcXml->modules->{$module->name}->depends->children() as $child) {
            $depends[] = $child->getName();
        }

        return $depends;
    }

    function run($modules)
    {
        $this->validateModulesInstallation();
        $modules = $this->getValidModules($modules);
        $warnings = array();
        foreach ($modules as $module) {
            try {
                $this->inst->exec('cp -r %s* .', $module->folder);
            } catch (\Exception $e) {
                $warnings[] = $e->getMessage();
            }
        }
        return compact('warnings');
    }

    protected function getValidModules($modulesNames)
    {
        $modules = array();
        $availableModules = $this->getAvailableModules();

        // check if modules exist
        foreach ($modulesNames as $moduleName) {
            if (!isset($availableModules[$moduleName])) {
                error("Module $moduleName not found");
            }
            $modules[$moduleName] = $availableModules[$moduleName];
        }

        // check if module folder is correct: doesn't contain extra stuff e.g. if modules installation is normal Magento
        foreach ($modules as $module) {
            $this->validateModule($module);
        }

        return $modules;
    }

    protected function validateModule($module)
    {
        $modulesInModule = listdir($module->folder . 'app/etc/modules', true);
        if (count($modulesInModule) > 1) {
            error("Foreign modules in {$module->name}, please check app/etc/modules in {$module->folder}, single file expected");
        }
        if (count($modulesInModule) < 1) {
            error("Module {$module->name} must contain file in app/etc/modules folder, please check {$module->folder}");
        }

        $etcXml = @simplexml_load_file($modulesInModule[0]);
        if (!$etcXml) {
            error("Module {$module->name} contain invalid XML in app/etc/modules folder, please check {$module->folder}");
        }

        if (count($etcXml->modules->children()) > 1) {
            error("Foreign modules in {$module->name} or invalid XML in {$module->folder}app/etc/modules/" . basename($modulesInModule[0]));
        }
    }

}
