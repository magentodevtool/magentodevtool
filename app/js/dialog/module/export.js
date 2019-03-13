ModuleExport = function (modules) {

    this.modules = modules;

    this.updateModulesList = function () {
        var container = $id('modulesContainer');
        var codePool = $id('codePool').value;
        var list = this.modules[codePool];

        if (count(list)) {
            container.innerHTML = '';
            var select = createSelectElement(list);
            select.className = 'wide';
            select.setAttribute('name', 'module');
            container.appendChild(select);
        } else {
            container.innerHTML = '- No modules found';
        }

        this.validateForm();
    }

    this.validateForm = function () {
        var data = getInputData('moduleExportForm');
        var valid = data.module && data.folder;
        $id('continueButton').disabled = !valid;
    }

    this.run = function () {
        inst.action(
            'module/export',
            getInputData('moduleExportForm'),
            function (response) {
                $id('moduleExportMessages').innerHTML = response.join('<br>');
            }
        );
    }

    this.updateModulesList();

}
