function ModuleSetup() {

    var me = this;
    me.projectModules = {};
    me.modules = {};
    var areDependsResolved = true;

    me.onPackageChange = function (input) {
        var containerId = input.getAttribute('id') + '.modules';
        $$('input[type=checkbox]', $id(containerId)).foreach(function (k, v) {
            if (!v.disabled) {
                v.checked = input.checked;
            }
        });
        checkDependencies()
    };

    me.onSetupClick = function () {
        if (!areDependsResolved) {
            alert('You need to resolve dependencies first');
            return;
        }
        var data = getInputData('module.setup.form');
        data.modules = getTrueKeys(data.modules);
        var resultDiv = $id('module.setup.result');
        resultDiv.innerHTML = '..';
        inst.action('module/setup', data, function (result) {
            resultDiv.innerHTML = result;
        });
    };

    me.pullModulesRepo = function () {
        inst.action('module/setup/pullModulesRepo', null, inst.reloadForm);
    };

    me.cloneModulesRepo = function () {
        inst.action('module/setup/cloneModulesRepo', null, inst.reloadForm);
    };

    me.onModuleChange = function () {
        checkDependencies();
    };

    var checkDependencies = function () {
        var dependsDiv = $id('module.setup.dependencies');
        var modules = getSelectedModules();
        areDependsResolved = true;

        var html = '';
        var sep = '';
        modules.foreach(function (i, moduleName) {
            var module = me.modules[moduleName];
            module.depends.foreach(function (i2, depModuleName) {

                var isDependResolved = getIsModuleDependResolved(moduleName, depModuleName);

                if (!isDependResolved) {
                    areDependsResolved = false;
                    var color = 'red';
                } else {
                    var color = 'green';
                }

                html += sep + module.name + ' <span style="color: ' + color + '">require</span> ' + depModuleName;
                sep = '<br>';
            });
        });

        dependsDiv.innerHTML = html;
        $id('module.setup.dependencies.container').style.visibility = (html === '' ? 'hidden' : 'visible');

    };

    var getIsModuleDependResolved = function (moduleName, depModuleName) {
        var modules = getSelectedModules();
        var isResolved = false;
        me.projectModules.foreach(function (codePool, projectModules) {
            projectModules.foreach(function (i, projectModuleName) {
                if (projectModuleName === depModuleName) {
                    isResolved = true;
                    return false;
                }
            });
        });
        if (!isResolved) {
            modules.foreach(function (i, moduleName) {
                if (moduleName === depModuleName) {
                    isResolved = true;
                    return false;
                }
            });
        }
        return isResolved;
    };

    var getSelectedModules = function () {
        return getTrueKeys(getInputData('module.setup.form').modules);
    }

}
