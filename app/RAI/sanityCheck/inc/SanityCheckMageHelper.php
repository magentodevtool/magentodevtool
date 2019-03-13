<?php

class SanityCheckMageHelper
{

    static function loadModulesConfiguration()
    {

        $disableLocalModules = !SanityCheckMageHelper::canUseLocalModules();

        $modules = Mage::getConfig()->getNode('modules')->children();

        $chain = array();

        foreach ($modules as $modName => $module) {

            if ($module->is('active')) {

                if ($disableLocalModules && ('local' === (string)$module->codePool)) {

                    continue;

                }

                $configFile = SanityCheckMageHelper::getModuleDir($modName, $module->codePool) . DS . 'config.xml';

                $chain[$modName] = array(
                    'config_path' => $configFile,
                    'code_pool' => (string)$module->codePool,
                    'depends' => $module->depends,
                );

            }
        }

        return $chain;

    }

    static function canUseLocalModules()
    {

        $disableLocalModules = (string)Mage::getConfig()->getNode('global/disable_local_modules');

        if (!empty($disableLocalModules)) {

            $disableLocalModules = (('true' === $disableLocalModules) || ('1' === $disableLocalModules));

        } else {

            $disableLocalModules = false;

        }

        if ($disableLocalModules && !defined('COMPILER_INCLUDE_PATH')) {

            set_include_path(
                BP . DS . 'app' . DS . 'code' . DS . 'community' . PS .
                BP . DS . 'app' . DS . 'code' . DS . 'core' . PS .
                BP . DS . 'lib' . PS .
                Mage::registry('original_include_path')
            );

        }

        return !$disableLocalModules;
    }

    static function getModuleDir($moduleName, $codePool)
    {
        $baseCodeDir = MAGE_ROOT . 'app' . DS . 'code';

        $dir = $baseCodeDir . DS . $codePool . DS . uc_words($moduleName, DS) . DS . 'etc';

        $dir = str_replace('/', DS, $dir);

        return $dir;
    }
}
