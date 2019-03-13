<?php

class SanityCheck
{

    static $elements_info, $rewrites_info, $modules_info;

    static $errors, $warnings, $fixed = array();

    static $output;


    static function renderAllConflicts($verbose = false, $toHTML = true)
    {

        self::init();

        self::findConflicts();

        self::describeAllErrors($verbose);

        return self::display($toHTML);

    }

    static function init()
    {

        $modules = SanityCheckMageHelper::loadModulesConfiguration();

        $elements = self::getElements($modules);

        self::$elements_info = self::fetchElementsInfo($elements);

        self::$rewrites_info = self::fetchRewritesInfo($elements);

        self::$modules_info = self::fetchModulesInfo($elements);

    }

    static function findConflicts()
    {

        $rewrites4check = self::$rewrites_info;

        self::cleanRewritesFrom(self::findInvalidBaseClasses(), $rewrites4check);

        self::cleanRewritesFrom(self::findInvalidRewriteClasses($rewrites4check), $rewrites4check);

        self::cleanRewritesFrom(self::findSelfRewrites($rewrites4check), $rewrites4check);

        self::cleanRewritesFrom(self::findForeignRewrites($rewrites4check), $rewrites4check);

        self::cleanRewritesFrom(self::findInvalidNotCoreRewrites($rewrites4check), $rewrites4check);

        self::cleanRewritesFrom(self::findLostBaseParents($rewrites4check), $rewrites4check);

        self::findDeprecatedBaseClasses($rewrites4check);

        // we don't need to check single rewrites (one rewrite for one model/block), so delete them
        self::cleanRewritesFrom(self::findSingleRewrites($rewrites4check), $rewrites4check);

        self::cleanRewritesFrom(self::findDuplicateRewrites($rewrites4check), $rewrites4check);

        self::cleanRewritesFrom(self::findAmbiguousRewrites($rewrites4check), $rewrites4check);

        self::cleanRewritesFrom(self::findModuleConflicts($rewrites4check), $rewrites4check);

        self::findFixedModuleConflicts($rewrites4check);

        self::cleanRewritesFrom(self::findMethodConflicts($rewrites4check), $rewrites4check);

        self::findNotUniqueMethodResolves($rewrites4check);

        self::findFixedMethodConflicts($rewrites4check);

    }

    static function describeAllErrors($verbose)
    {

        self::$output = array();

        self::$output = array_merge(

        //self::describeInvalidBaseClasses(),

        //self::describeInvalidRewriteClasses(),

        //self::describeSelfRewrites(),

        //self::describeForeignRewrites(),

        //self::describeInvalidNotCoreRewrites(),

        //self::describeLostBaseParents(),

        //self::describeDuplicateRewrites(),

        //self::describeAmbiguousRewrites(),

            self::describeModuleConflicts(),

            self::describeMethodConflicts()

        );

        if ($verbose) {

            self::$output = array_merge(

                self::$output,

                self::describeDeprecatedBaseClasses(),

                self::describeMethodWarnings(),

                self::describeFixedModuleConflicts(),

                self::describeFixedMethodConflicts()

            );

        }
    }

    static function display($toHTML)
    {

        $result = '';

        if ($toHTML) {

            $result = self::output2HTML();

        } else {

            $result = self::output2Text();

        }

        if (empty($result)) {

            $result = "No problems found. Carry on!\n";

        }

        return $result;

    }

    static function output2Text()
    {

        $text = '';

        foreach (self::$output as $message) {

            $text .= $message['name'] . ": ";

            $text .= $message['description'] . "\n";

            $text .= (!isset($message['fixed'])) ? "How to fix: " : "Fixes: ";

            $text .= $message['fix'] . "\n";

            $text .= "\n";

        }

        return $text;

    }

    static function output2HTML()
    {

        return self::$output;

    }

    static function describeInvalidBaseClasses()
    {

        $describe = array();

        foreach (self::$errors['invalid_base_classes'] as $key => $class) {

            $key_arr = explode('/', $key);

            $model = $key_arr[2];

            $section = str_replace('_' . $model, '', $class);

            $description = "nonexistent base class {$class} on element: {$key}";

            $describe[] = array(

                'name' => 'CONFIGURATION ERROR',

                'description' => $description,

                'fix' => "Correct <class>{$section}</class> section into config.xml or create this class",

            );
        }

        return $describe;

    }

    static function describeInvalidRewriteClasses()
    {

        $describe = array();

        foreach (self::$errors['invalid_rewrite_classes'] as $key => $modules) {

            foreach ($modules as $module => $classes) {

                $class_names = implode(", ", $classes);

                $is_several_classes = strpos($class_names, ',');

                $handling = $is_several_classes ? "classes" : "class";

                $description = "nonexistent {$handling} {$class_names}";

                $description .= " on module {$module}";

                $path = explode('_', $module);

                $config_path = "/{$path[0]}/{$path[1]}/etc/config.xml";

                $describe[] = array(

                    'name' => 'CONFIGURATION ERROR',

                    'description' => $description,

                    'fix' => "Correct {$handling} declaration into {$config_path} or create this {$handling}",

                );
            }
        }

        return $describe;

    }

    static function describeSelfRewrites()
    {

        $describe = array();

        foreach (self::$errors['self_rewrites'] as $key => $modules) {

            foreach ($modules as $module => $classes) {

                $path = explode('_', $module);

                $config_path = "/{$path[0]}/{$path[1]}/etc/config.xml";

                $describe[] = array(

                    'name' => 'CONFIGURATION ERROR',

                    'description' => "self rewrites on module: {$module}",

                    'fix' => "Self rewrites is senseless, delete it from {$config_path}",

                );

            }
        }

        return $describe;

    }

    static function describeForeignRewrites()
    {

        $describe = array();

        foreach (self::$errors['foreign_rewrites'] as $key => $modules) {

            foreach ($modules as $module => $classes) {

                $class_names = implode(", ", $classes);

                $is_several_classes = strpos($class_names, ',');

                $handling = $is_several_classes ? "classes" : "class";

                $path = explode('_', $module);

                $config_path = "/{$path[0]}/{$path[1]}/etc/config.xml";

                $describe[] = array(

                    'name' => 'CONFIGURATION ERROR',

                    'description' => "foreign rewrites on module {$module} for {$handling} {$class_names}",

                    'fix' => "Right module can rewrite only to itself classes, so delete all foreign rewrites from {$config_path}",

                );

            }
        }

        return $describe;

    }

    static function describeInvalidNotCoreRewrites()
    {

        $describe = array();

        foreach (self::$errors['invalid_not_core_rewrites'] as $key => $modules) {

            foreach ($modules as $module => $base_module) {

                $path = explode('_', $module);

                $config_path = "/{$path[0]}/{$path[1]}/etc/config.xml";

                $fix = "Right module can override classes only from 'core' code pool or from dependent module.\n";

                $fix .= "So change base class in {$config_path} or add dependence from module " . $base_module;

                $describe[] = array(

                    'name' => 'CONFIGURATION ERROR',

                    'description' => "invalid base class for module: {$module}",

                    'fix' => $fix,

                );

            }
        }

        return $describe;

    }

    static function describeLostBaseParents()
    {

        $describe = array();

        foreach (self::$errors['lost_base_parents'] as $key => $modules) {

            foreach ($modules as $module => $parents) {

                $expected_parent = uc_words($parents['expected_parent'], '_', '_');

                $reflectionInfo = self::getReflectionInfo($parents['class']);

                $description = "parent class is lost for element {$key}, module {$module}";

                $fix = "file: {$reflectionInfo['path']}\n";

                $fix .= "class {$parents['class']} extends {$expected_parent}";

                $describe[] = array(

                    'name' => 'CONFIGURATION ERROR',

                    'description' => $description,

                    'fix' => $fix,

                );
            }
        }

        return $describe;

    }

    static function describeDuplicateRewrites()
    {

        $describe = array();

        foreach (self::$errors['duplicate_rewrites'] as $key => $modules) {

            foreach ($modules as $module => $classes) {

                $class = end($classes);

                $path = explode('_', $module);

                $config_path = "/{$path[0]}/{$path[1]}/etc/config.xml";

                $describe[] = array(

                    'name' => 'CONFIGURATION ERROR',

                    'description' => "duplicate class {$class} rewrites on module {$module}",

                    'fix' => "Delete all duplicate rewrites except one from {$config_path}",

                );

            }
        }

        return $describe;

    }

    static function describeAmbiguousRewrites()
    {

        $describe = array();

        foreach (self::$errors['ambiguous_rewrites'] as $key => $modules) {

            foreach ($modules as $module => $classes) {

                $class_names = implode(", ", $classes);

                $is_several_classes = strpos($class_names, ',');

                $handling = $is_several_classes ? "classes" : "class";

                $path = explode('_', $module);

                $config_path = "/{$path[0]}/{$path[1]}/etc/config.xml";

                $describe[] = array(

                    'name' => 'CONFIGURATION ERROR',

                    'description' => "ambiguous rewrites for module {$module}, {$handling} {$class_names}",

                    'fix' => "One section should have only one rewrite, delete unnecessary rewrites from {$config_path}",

                );

            }
        }

        return $describe;

    }

    static function describeModuleConflicts()
    {

        $describe = array();

        foreach (self::$errors['module_conflicts'] as $key => $classes) {

            $right_chain = self::getExpectedExtendsChain($key);

            $fix = '';

            $child = '';

            foreach ($right_chain as $class) {

                if (!empty($child)) {

                    $reflectionInfo = self::getReflectionInfo($child);

                    $fix .= "\n	file: {$reflectionInfo['path']}\n";

                    $fix .= "class " . $child . " extends " . $class;
                }

                $child = $class;

            }

            $class_names = implode(', ', $classes);

            $is_several_classes = strpos($class_names, ',');

            $handling1 = $is_several_classes ? "classes" : "class";

            $handling2 = $is_several_classes ? "are" : "is";

            $description = "{$handling1} {$class_names} {$handling2} lost in element {$key}";

            $describe[] = array(

                'name' => 'MODULE CONFLICT',

                'description' => $description,

                'fix' => "{$fix}",

            );

        }

        return $describe;

    }

    static function describeFixedModuleConflicts()
    {

        $describe = array();

        foreach (self::$fixed['module_conflicts'] as $key => $classes) {

            $right_chain = self::getRealExtendsChain($key);

            $fix = '';

            $child = '';

            foreach ($right_chain as $class) {

                if (!empty($child)) {

                    $reflectionInfo = self::getReflectionInfo($child);

                    $fix .= "\nfile: {$reflectionInfo['path']}\n";

                    $fix .= "class " . $child . " extends " . $class;
                }

                $child = $class;

            }

            $describe[] = array(

                'name' => 'FIXED MODULE CONFLICT',

                'description' => "element {$key}",

                'fix' => "{$fix}",

                'fixed' => true,

            );

        }

        return $describe;

    }

    static function describeMethodConflicts()
    {

        $describe = array();

        foreach (self::$errors['method_conflicts'] as $key => $methods) {

            foreach ($methods as $method => $classes) {

                foreach ($classes as $child => $parent) {

                    $reflectionInfo = self::getReflectionInfo($child);

                    $fix = "Merge methods and then add next comment before function {$method}() in the file {$reflectionInfo['path']}:\n";

                    $fix .= "* resolved with {$parent}::{$method}";

                    $description = "{$parent}->{$method}() method is overridden by {$child}->{$method}() in section {$key}";

                    $describe[] = array(

                        'name' => 'METHOD CONFLICT',

                        'description' => $description,

                        'fix' => "{$fix}",

                    );
                }
            }
        }

        return $describe;

    }

    static function describeFixedMethodConflicts()
    {

        $describe = array();

        foreach (self::$fixed['method_conflicts'] as $key => $methods) {

            foreach ($methods as $method => $classes) {

                foreach ($classes as $child => $parent) {

                    $reflectionInfo = self::getReflectionInfo($child);

                    $fix = "Added comment before function {$method}() in the file {$reflectionInfo['path']}:\n";

                    $fix .= "* resolved with {$parent}::{$method}";

                    $description = "element {$key}";

                    $describe[] = array(

                        'name' => 'FIXED METHOD CONFLICT',

                        'description' => $description,

                        'fix' => "{$fix}",

                        'fixed' => true,

                    );
                }
            }
        }

        return $describe;

    }

    static function describeDeprecatedBaseClasses()
    {

        $describe = array();

        foreach (self::$warnings['deprecated_base_classes'] as $key => $modules) {

            foreach ($modules as $module => $info) {

                $describe[] = array(

                    'name' => 'CONFIG WARNING',

                    'description' => "module {$module} use deprecated namespace - {$info['deprecated']}",

                    'fix' => "use recommended namespace - {$info['recommended']}",

                );
            }
        }

        return $describe;

    }

    static function describeMethodWarnings()
    {

        $describe = array();

        foreach (self::$warnings['not_unique_method_resolves'] as $key => $methods) {

            foreach ($methods as $method => $classes) {

                $class = key($classes);

                $reflectionInfo = self::getReflectionInfo($class);

                $describe[] = array(

                    'name' => 'METHOD WARNING',

                    'description' => "{$class}->{$method}() method in element {$key} contains foreign resolving comments",

                    'fix' => "Delete all resolving comments except one before {$method}() method in file {$reflectionInfo['path']}",

                );
            }
        }

        return $describe;

    }

    static function getElements($modules)
    {

        $elements = array();

        $sections = array('models', 'blocks');

        foreach ($modules as $name => $module) {

            $config = simplexml_load_file($module['config_path']);

            $module_depends = self::fetchModuleDepends($module);

            if (isset($config->global)) {

                foreach ($config->global as $global) {

                    foreach ($sections as $section_name) {

                        if (isset($global->$section_name)) {

                            foreach ($global->$section_name as $section) {

                                foreach ($section->children() as $scope => $sub_section) {

                                    if (isset($sub_section->rewrite)) {

                                        foreach ($sub_section->rewrite as $rewrite) {

                                            foreach ($rewrite->children() as $key => $class) {

                                                $k = $section_name . '/' . $scope . '/' . $key;
                                                $elements[$k]['rewrites'][$name]['code_pool'] = $module['code_pool'];
                                                $elements[$k]['rewrites'][$name]['classes'][] = (string)$class;
                                                $elements[$k]['rewrites'][$name]['depends'] = $module_depends;

                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $classes = self::fetchAllClasses($modules, $sections);

        self::addBaseClasses($elements, $classes);

        return $elements;

    }

    static function fetchModuleDepends($module)
    {

        $module_depends = array();

        if (isset($module['depends'])) {

            foreach ($module['depends'] as $dependence) {

                foreach ($dependence as $dependence_module => $dependence_info) {

                    $module_depends[] = $dependence_module;

                }
            }
        }

        return $module_depends;

    }

    static function fetchAllClasses($modules, $sections)
    {

        $classes = array();

        foreach ($sections as $section_name) {

            foreach ($modules as $name => $module) {

                $config = simplexml_load_file($module['config_path']);

                if ($section = $config->global->$section_name) {

                    foreach ($section->children() as $scope => $section_items) {

                        if ($class = $section_items->class) {

                            $classes[$section_name . '/' . $scope] = array(
                                'code_pool' => $module['code_pool'],
                                'module' => $name,
                                'class' => (string)$class,
                            );

                            if ($deprecated_scope = $section_items->deprecatedNode) {

                                $deprecated_scope_suffix = end(explode('_', (string)$deprecated_scope));
                                $scope_suffix = end(explode('_', $scope));
                                $class = str_replace(uc_words($scope_suffix), uc_words($deprecated_scope_suffix),
                                    $class);

                                $classes[$section_name . '/' . $deprecated_scope] = array(
                                    'code_pool' => $module['code_pool'],
                                    'module' => $name,
                                    'class' => $class,
                                    'deprecated' => (string)$deprecated_scope,
                                    'recommended' => $scope,
                                );
                            }
                        }
                    }
                }
            }
        }

        self::addPredefinedClasses($classes);

        return $classes;
    }

    static function addPredefinedClasses(&$classes)
    {

        $classes['models/core'] = array(
            'code_pool' => 'core',
            'module' => 'Mage_Core',
            'class' => 'Mage_Core_Model',
        );
    }

    static function fetchElementsInfo($elements)
    {

        $elements_info = array();

        foreach ($elements as $key => $element) {

            $elements_info[$key] = array(
                'base_class' => $element['base_class'],
                'deprecated' => $element['deprecated'],
                'recommended' => $element['recommended'],
            );

        }

        return $elements_info;

    }

    static function fetchModulesInfo($elements)
    {

        $modules_info = array();

        foreach ($elements as $element) {

            foreach ($element['rewrites'] as $module => $rewrite) {

                $modules_info[$module]['code_pool'] = $rewrite['code_pool'];
                $modules_info[$module]['depends'] = $rewrite['depends'];

            }
        }

        return $modules_info;

    }

    static function fetchRewritesInfo($elements)
    {

        $rewrites_info = array();

        foreach ($elements as $key => $element) {

            foreach ($element['rewrites'] as $module => $rewrite) {

                $rewrites_info[$key][$module] = $rewrite['classes'];

            }
        }

        return $rewrites_info;

    }

    static function addBaseClasses(&$elements, $classes)
    {

        foreach ($elements as $key => $rewrite) {

            $key_arr = explode('/', $key);

            $scope = $key_arr[0] . '/' . $key_arr[1];

            $elements[$key]['base_class'] = $classes[$scope]['class'] . '_' . $key_arr[2];

            if (isset($classes[$scope]['deprecated'])) {

                $elements[$key]['deprecated'] = $classes[$scope]['deprecated'];
                $elements[$key]['recommended'] = $classes[$scope]['recommended'];

            }

        }
    }

    static function findInvalidBaseClasses()
    {

        self::$errors['invalid_base_classes'] = array();

        foreach (self::$elements_info as $key => $element) {

            if (!class_exists($element['base_class'])) {

                self::$errors['invalid_base_classes'][$key] = $element['base_class'];

            }
        }

        return self::$errors['invalid_base_classes'];

    }

    static function findInvalidRewriteClasses($rewrites4check)
    {

        self::$errors['invalid_rewrite_classes'] = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            foreach ($element_rewrites as $module => $rewrites) {

                foreach ($rewrites as $class) {

                    if (!class_exists($class)) {

                        self::$errors['invalid_rewrite_classes'][$key][$module][] = $class;

                    }
                }

            }
        }

        return self::$errors['invalid_rewrite_classes'];

    }

    static function findSelfRewrites($rewrites4check)
    {

        self::$errors['self_rewrites'] = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            $key_arr = explode('/', $key);

            $namespace = $key_arr[1];

            foreach ($element_rewrites as $module => $rewrites) {

                if ($module == $namespace) {

                    self::$errors['self_rewrites'][$key][$module] = $rewrites;

                }

            }
        }

        return self::$errors['self_rewrites'];

    }

    static function findForeignRewrites($rewrites4check)
    {

        self::$errors['foreign_rewrites'] = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            foreach ($element_rewrites as $module => $rewrites) {

                foreach ($rewrites as $class) {

                    if (strpos($class, $module . "_") === false) {

                        self::$errors['foreign_rewrites'][$key][$module][] = $class;
                    }
                }
            }
        }

        return self::$errors['foreign_rewrites'];

    }

    static function findInvalidNotCoreRewrites($rewrites4check)
    {

        self::$errors['invalid_not_core_rewrites'] = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            $key_arr = explode('/', $key);

            $namespace = $key_arr[1];

            foreach ($element_rewrites as $module => $rewrites) {

                if (isset(self::$modules_info[$namespace])) {

                    $in_dependence = false;

                    foreach (self::$modules_info[$module]['depends'] as $dependence_module) {

                        if ($namespace == $dependence_module) {

                            $in_dependence = true;

                        }

                    }

                    if (!$in_dependence) {

                        self::$errors['invalid_not_core_rewrites'][$key][$module] = $namespace;

                    }
                }
            }
        }

        return self::$errors['invalid_not_core_rewrites'];

    }

    static function findLostBaseParents($rewrites4check)
    {

        self::$errors['lost_base_parents'] = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            $real_extends_chains = self::getRealExtendsChain($key);

            $real_parent = strtolower(end($real_extends_chains));

            $expected_parent = strtolower(self::$elements_info[$key]['base_class']);

            foreach ($element_rewrites as $module => $classes) {

                if ($real_parent != $expected_parent) {

                    self::$errors['lost_base_parents'][$key][$module] = array(
                        'expected_parent' => $expected_parent,
                        'real_parent' => $real_parent,
                        'class' => $classes[0],
                    );

                }
            }
        }

        return self::$errors['lost_base_parents'];

    }

    static function findDeprecatedBaseClasses($rewrites4check)
    {

        self::$warnings['deprecated_base_classes'] = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            foreach ($element_rewrites as $module => $classes) {

                if (self::$elements_info[$key]['deprecated']) {

                    self::$warnings['deprecated_base_classes'][$key][$module] = array(
                        'deprecated' => self::$elements_info[$key]['deprecated'],
                        'recommended' => self::$elements_info[$key]['recommended'],
                    );
                }
            }
        }

        return self::$warnings['deprecated_base_classes'];

    }

    static function findSingleRewrites($rewrites4check)
    {

        $single_rewrites = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            if (count($element_rewrites) == 1) {

                foreach ($element_rewrites as $module => $rewrites) {

                    if (count($rewrites) == 1) {

                        $single_rewrites[$key][$module] = $rewrites;

                    }
                }
            }
        }

        return $single_rewrites;

    }

    static function findDuplicateRewrites($rewrites4check)
    {

        self::$errors['duplicate_rewrites'] = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            foreach ($element_rewrites as $module => $rewrites) {

                if (count($rewrites) > 1 && count(array_unique($rewrites)) == 1) {

                    self::$errors['duplicate_rewrites'][$key][$module] = $rewrites;

                }

            }
        }

        return self::$errors['duplicate_rewrites'];

    }

    static function findAmbiguousRewrites($rewrites4check)
    {

        self::$errors['ambiguous_rewrites'] = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            foreach ($element_rewrites as $module => $rewrites) {

                $unique_rewrites = array_unique($rewrites);

                if (count($unique_rewrites) > 1) {

                    self::$errors['ambiguous_rewrites'][$key][$module] = $rewrites;

                }

            }
        }

        return self::$errors['ambiguous_rewrites'];

    }

    static function findModuleConflicts($rewrites4check)
    {

        self::$errors['module_conflicts'] = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            $expected_extends_chains = self::getExpectedExtendsChain($key);
            $real_extends_chains = self::getRealExtendsChain($key);

            if ($diff = array_udiff($expected_extends_chains, $real_extends_chains, 'strcasecmp')) {

                self::$errors['module_conflicts'][$key] = $diff;

            }
        }

        return self::$errors['module_conflicts'];

    }

    static function findFixedModuleConflicts($rewrites4check)
    {

        self::$fixed['module_conflicts'] = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            $expected_extends_chains = self::getExpectedExtendsChain($key);

            $real_extends_chains = self::getRealExtendsChain($key);

            if (!array_udiff($expected_extends_chains, $real_extends_chains, 'strcasecmp')) {

                self::$fixed['module_conflicts'][$key] = $real_extends_chains;

            }
        }

        return self::$fixed['module_conflicts'];

    }

    static function getSectionExpectedExtendsChain($key)
    {

        $expected_extends_chains = array();

        foreach (array_reverse(self::$rewrites_info[$key]) as $rewrites) {

            foreach ($rewrites as $class) {

                $expected_extends_chains[] = $class;

            }

        }

        $base_class = self::$elements_info[$key]['base_class'];

        array_push($expected_extends_chains, $base_class);

        return $expected_extends_chains;

    }

    static function getSectionRealExtendsChain($key)
    {

        $expected_extends_chain = self::getExpectedExtendsChain($key);

        $final_class = reset($expected_extends_chain);

        array_pop($expected_extends_chain);

        $real_extends_chain = array();

        $parent = true;

        $class = $final_class;

        $real_extends_chain[] = $final_class;

        while ($parent && in_array(strtolower($class), array_map('strtolower', $expected_extends_chain))) {

            $parent = self::getParent($class);

            $class = $parent;

            if (!empty($parent)) {

                $real_extends_chain[] = $parent;

            }

        }

        return $real_extends_chain;

    }

    static function getReflectionInfo($class)
    {


        if (class_exists($class)) {

            $class_reflect = new ReflectionClass($class);

            $parent = $class_reflect->getParentClass();

            if (is_object($parent)) {

                $res['parent'] = $parent->getName();
                $path = str_replace(MAGE_ROOT, '', $class_reflect->getFileName());
                $res['path'] = $path;

                return $res;

            }

        }

        return false;

    }

    static function findMethodConflicts($rewrites4check)
    {

        self::$errors['method_conflicts'] = array();

        $overrides = self::getAllOverrides($rewrites4check);

        $resolves = self::getAllResolves($rewrites4check);

        self::$errors['method_conflicts'] = self::getMethodConflicts($overrides, $resolves);

        return self::$errors['method_conflicts'];

    }

    static function findFixedMethodConflicts($rewrites4check)
    {

        self::$fixed['method_conflicts'] = array();

        $overrides = self::getAllOverrides($rewrites4check);

        $resolves = self::getAllResolves($rewrites4check);

        self::$fixed['method_conflicts'] = self::getMethodFixedConflicts($overrides, $resolves);

        return self::$fixed['method_conflicts'];

    }

    static function getAllOverrides($rewrites4check)
    {

        $overrides = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            $overrides[$key] = self::getSectionOverridenMethods($element_rewrites, true);

        }

        self::sortOverridesByExtendsChain($overrides);

        return $overrides;

    }

    static function sortOverridesByExtendsChain(&$overrides)
    {

        foreach ($overrides as $key => &$section_overrides) {

            foreach ($section_overrides as &$classes) {

                $classes = self::filterRewritesBy($key, $classes);

            }
        }
    }

    static function getAllResolves($rewrites4check)
    {

        $resolves = array();

        foreach ($rewrites4check as $key => $element_rewrites) {

            $section_overrides = self::getSectionOverridenMethods($element_rewrites);

            foreach ($section_overrides as $method_name => $method_definitions) {

                $resolves[$key][$method_name] = self::fetchMethodResolvedConflicts($method_definitions);

            }
        }

        return $resolves;

    }

    static function getMethodConflicts($overrides, $resolves)
    {

        $methods_conflicts = array();

        foreach ($overrides as $key => $section_overrides) {

            foreach ($section_overrides as $method => $classes) {

                foreach ($classes as $i => $class) {

                    if (isset($classes[$i + 1])) {

                        $parent_class = $classes[$i + 1];

                        if (
                            (!isset($resolves[$key][$method][strtolower($class)]))
                            ||
                            (!in_array(
                                strtolower($parent_class),
                                array_map('strtolower', $resolves[$key][$method][strtolower($class)])
                            ))
                        ) {

                            $methods_conflicts[$key][$method][$class] = $parent_class;

                        }
                    }
                }
            }
        }

        return $methods_conflicts;

    }

    static function getMethodFixedConflicts($overrides, $resolves)
    {

        $methods_fixed_conflicts = array();

        foreach ($overrides as $key => $section_overrides) {

            foreach ($section_overrides as $method => $classes) {

                foreach ($classes as $i => $class) {

                    if (isset($classes[$i + 1])) {

                        $parent_class = $classes[$i + 1];

                        if (isset($resolves[$key][$method][strtolower($class)])) {

                            if (current($resolves[$key][$method][strtolower($class)]) == strtolower($parent_class)) {

                                $methods_fixed_conflicts[$key][$method][$class] = $parent_class;

                            }
                        }
                    }
                }
            }
        }

        return $methods_fixed_conflicts;

    }

    static function findNotUniqueMethodResolves($rewrites4check)
    {

        self::$warnings['not_unique_method_resolves'] = array();

        $resolves = self::getAllResolves($rewrites4check);

        foreach ($resolves as $key => $methods) {

            foreach ($methods as $method => $classes) {

                foreach ($classes as $class => $resolves) {

                    if (count($resolves) > 1) {

                        self::$warnings['not_unique_method_resolves'][$key][$method][$class] = $resolves;

                    }
                }
            }
        }

        return self::$warnings['not_unique_method_resolves'];

    }

    static function getRealExtendsChain($key)
    {

        static $real_extends_cache = array();

        if (!isset($real_extends_cache[$key])) {

            $real_extends_cache[$key] = self::getSectionRealExtendsChain($key);

        }

        return $real_extends_cache[$key];

    }

    static function getExpectedExtendsChain($key)
    {

        static $expected_extends_cache = array();

        if (!isset($expected_extends_cache[$key])) {

            $expected_extends_cache[$key] = self::getSectionExpectedExtendsChain($key);

        }

        return $expected_extends_cache[$key];

    }

    static function filterRewritesBy($key, $classes)
    {

        $real_extends_chain = self::getRealExtendsChain($key);

        foreach ($real_extends_chain as $class_key => $class) {

            if (!in_array(strtolower($class), array_map('strtolower', $classes))) {

                unset($real_extends_chain[$class_key]);

            }

        }

        return $real_extends_chain;

    }

    static function fetchMethodResolvedConflicts($method)
    {

        $method_resolved_conflicts = array();

        foreach ($method as $method_definition) {

            if ($resolved = self::getResolvedMethodConflicts($method_definition)) {

                $class = strtolower($method_definition->getDeclaringClass()->getName());

                $method_resolved_conflicts[$class] = $resolved;

            }

        }

        return $method_resolved_conflicts;

    }

    static function getSectionOverridenMethods($element_rewrites, $as_array = false)
    {

        $all_methods = array();

        foreach ($element_rewrites as $module => $rewrites) {

            foreach ($rewrites as $class) {

                $class_object = new ReflectionClass($class);
                $methods = $class_object->getMethods();

                foreach ($methods as $method) {

                    if ($method->getDeclaringClass()->getName() == $class) {

                        $all_methods[$method->getName()][] = (!$as_array) ? $method : strtolower($class);

                    }
                }
            }
        }

        $overriden_methods = self::deleteUniqueMethods($all_methods);

        return $overriden_methods;

    }

    static function getParent($class)
    {

        $parent = false;

        $reflectionInfo = self::getReflectionInfo($class);

        $parent = $reflectionInfo['parent'];

        return $parent;

    }


    static function cleanRewritesFrom($rewrites4clean, &$rewrites4check, $drop_by_main_key = true)
    {

        foreach ($rewrites4clean as $key => $modules) {

            if ($drop_by_main_key) {

                unset($rewrites4check[$key]);

            } else {

                if (is_array($modules)) {

                    foreach ($modules as $module => $classes) {

                        if (is_array($classes)) {

                            foreach ($classes as $class_key => $class) {

                                unset($rewrites4check[$key][$module][$class_key]);

                            }
                        }

                        if (empty($rewrites4check[$key][$module])) {

                            unset($rewrites4check[$key][$module]);

                        }
                    }
                }

                if (empty($rewrites4check[$key])) {

                    unset($rewrites4check[$key]);

                }
            }
        }
    }

    static function getResolvedMethodConflicts($method)
    {

        $resolved_method_conflicts = array();

        $comments = $method->getDocComment();

        if (!empty($comments)) {

            $comments = substr($comments, 3, -2); //deleting service symbols according to T_DOC_COMMENT format
            $comments = explode('*', $comments);

            foreach ($comments as $key => &$comment) {

                $comment = trim($comment);

                if (empty($comment)) {

                    unset($comments[$key]);

                } else {

                    $matches = array();
                    preg_match('~^resolved +with +(.+)::(.+) *$~ism', $comment, $matches);

                    if (empty($matches)) {

                        unset($comments[$key]);

                    } else {

                        $resolved_class = $matches[1];
                        $resolved_method = $matches[2];

                        if ($method->getName() == $resolved_method) {

                            $resolved_method_conflicts[] = strtolower($resolved_class);

                        }
                    }
                }
            }
        }

        return $resolved_method_conflicts;
    }

    static function deleteUniqueMethods($all_methods)
    {

        $override_methods = array();

        foreach ($all_methods as $method => $classes) {

            if (count($classes) != 1) {

                $override_methods[$method] = $classes;

            }
        }

        return $override_methods;

    }

}

function vd($data, $title = null)
{

    echo "<pre>";
    if (!is_null($title)) {

        $title = strtoupper($title);

        print_r("================{$title}===================\n");

    }

    print_r($data);
    echo "<pre>";

}

function vde($data)
{

    print_r($data);

    exit;

}