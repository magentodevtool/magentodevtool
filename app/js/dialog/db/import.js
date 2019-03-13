DbImport = function () {

    var me = this;

    me.actionValue = null;
    me.remoteInstallationName = null;
    me.database = null;
    me.dump = null;
    me.dumpFileName = null;
    me.rmArchiveAfterExtract = null;
    me.removeDump = null;
    me.tablesMode = null;
    me.tables = 'all';
    me.dumpInfo = null;
    me.dumpDataSize = 0;
    me.adjustToDev = null;
    me.dumpImportInProgress = null;

    me.complete = function () {
    };

    var typesActions = {
        'importLocalDump': [
            'dump/extract',
            'dump/getInfo',
            'start'
        ],
        'importRemoteDump': [
            'dump/download',
            'dump/extract',
            'dump/getInfo',
            'start'
        ],
        'importRemoteDatabase': [
            'dump/create',
            'dump/download',
            'dump/remove',
            'dump/extract',
            'dump/getInfo',
            'start'
        ]
    };

    me['dump/create_before'] = function () {
        $id('importDbMessages').innerHTML += 'exporting ' + me.remoteInstallationName + ' database..';

        var date = new Date();
        var dumpName = date.getTime() + '.' + date.getMilliseconds();

        me.dumpFileName = dumpName;
        me.dump = 'backups/' + dumpName + '.sql.gz';
    };

    me['dump/create_after'] = function (response) {
        if (response.success) {
            $id('importDbMessages').innerHTML += ' done<br>';
        } else {
            me.enableForm();
            $id('importDbMessages').innerHTML += ' fail';
            if (response.result.wereErrors) {
                $id('importDbMessages').innerHTML += ' (please check file ' + dumpName + '.sql.gz.errors on ' + me.remoteInstallationName + ')';
            }
        }
    };

    me['dump/download_before'] = function () {
        $id('importDbMessages').innerHTML += 'downloading ' + me.dump + ' from ' + me.remoteInstallationName + '..';
    };

    me['dump/download_after'] = function (response) {
        if (response.success) {
            me.rmArchiveAfterExtract = true;
            me.removeDump = true;
            $id('importDbMessages').innerHTML += ' done<br>';
        } else {
            $id('importDbMessages').innerHTML += ' fail';
            me.enableForm();
        }
    };

    me['dump/extract_before'] = function () {
        if (/\.gz$/.test(me.dump)) {
            me.removeDump = true;
            $id('importDbMessages').innerHTML += 'extracting ' + me.dump + '...';
        }
    };

    me['dump/extract_after'] = function (response) {
        if (response.success) {
            if (/\.gz$/.test(me.dump)) {
                me.dump = me.dump.replace(/\.gz$/, '');
                $id('importDbMessages').innerHTML += ' done<br>';
            }
        } else {
            me.enableForm();
            $id('importDbMessages').innerHTML += '<br>' + response.message;
        }
    };

    me['dump/getInfo_after'] = function (dumpInfo) {
        me.dumpInfo = dumpInfo.result;
    };

    me['start_before'] = function () {
        if (me.tablesMode == 'selected' && me.tables == 'all') {
            me.renderTablesForm();
            return false;
        }

        $id('importDbMessages').innerHTML += 'importing ' + me.dump + '..';

        // init progress
        me.dumpImportInProgress = true;
        me.calculateDumpDataSize();
        me.importProgress();
    };

    me['start_after'] = function (response) {
        if (response.success) {
            $id('importDbMessages').innerHTML += ' done';
        } else {
            $id('importDbMessages').innerHTML += ' fail';
            if (response && response.wereErrors) {
                $id('importDbMessages').innerHTML += ' (please check ' + me.dump + '.errors)';
            }
            me.enableForm();
            me.hideProgress();
        }
        me.dumpImportInProgress = false;
    };

    var typeActions;
    var lastTypeAction;

    me.init = function () {
        me.refreshRemoteInstallations();
        me.refreshDbDumps();
        me.validateForm();
    };

    me.action = function (action, onSuccess, block) {

        var onActionBefore = me[action + '_before'];
        var onActionAfter = me[action + '_after'];

        if (typeof onActionBefore === 'function' && onActionBefore() === false) {
            return false;
        }

        if (typeActions != null && typeActions.indexOf(action) !== -1) lastTypeAction = action;

        return inst.action(
            'db/import/' + action,
            me,
            function (response) {
                typeof onActionAfter === 'function' ? onActionAfter(response) : false;
                if (response && response.success !== false) {
                    if (onSuccess) {
                        typeof onSuccess === 'function' ? onSuccess(response) : me[onSuccess](response);
                    }
                }
            },
            block
        );
    };

    me.onActionChange = function (select) {

        var options = select.options;

        for (var i = 0; i < options.length; i++) {
            var fields = $id(options[i].value + 'Fields');
            if (fields) {
                fields.style.display = select.value == options[i].value ? 'table-row-group' : 'none';
            }
        }

        showHide($id('tablesMode.container'), select.value !== 'alreadyFixed');

        me.validateForm();

    };

    me.refreshRemoteInstallations = function (list) {

        $id('remoteDump').style.display = 'none';

        list = list == null ? server.action('project/getRemoteInstallations', inst, null) : list;
        var containers = $$('.remote-installations-list');

        for (var i = 0; i < containers.length; i++) {
            var container = containers[i];
            var select = container.getElementsByTagName('select')[0];
            var zeroOption = select.getAttribute('zero-option');

            if (list.length) {

                select.innerHTML = '';

                if (zeroOption) {
                    var opt = document.createElement('option');
                    opt.innerHTML = zeroOption;
                    opt.value = 0;
                    select.options.add(opt);
                }

                for (var j = 0; j < list.length; j++) {
                    var opt = document.createElement('option');
                    opt.innerHTML = list[j];
                    select.options.add(opt);
                }

                $('.empty-message', container).style.display = 'none';
                select.style.display = 'inline';

            } else {

                select.style.display = 'none';
                $('.empty-message', container).style.display = 'inline';

            }

        }

        me.validateForm();

    };

    me.refreshDbDumps = function () {

        me.collectFormData();
        me.action('dumps/list', function (response) {

            var container = $id('dbDumpsContainer');

            if (count(response.list)) {

                container.innerHTML = '';

                var select = createSelectElement(response.list);
                select.className = 'wide';
                select.setAttribute('name', 'dump');

                container.appendChild(select);

            } else {

                container.innerHTML = '- You have no dumps within var folder, please add and press';

            }

            me.validateForm();

        });

    };

    me.remoteDumpsInstallationChange = function (select) {
        if (select.value === '0') {
            $id('remoteDump').style.display = 'none';
        } else {
            me.refreshRemoteDumps();
        }
    };

    me.refreshRemoteDumps = function () {

        me.collectFormData();
        me.action('dumps/list', function (response) {

            var container = $id('remoteDumpsContainer');

            if (count(response.list)) {

                container.innerHTML = '';

                var select = createSelectElement(response.list);
                select.className = 'wide';
                select.setAttribute('name', 'dump');

                container.appendChild(select);

            } else {
                container.innerHTML = '- ' + me.remoteInstallationName + ' have no dumps within var folder';
            }

            $id('remoteDump').style.display = 'table-row';

            me.validateForm();

        });

    };

    me.validateForm = function () {

        var data = getInputData('importDbForm');
        var valid;
        switch (data.action) {
            case 'importRemoteDatabase':
                valid = data.remote;
                break;
            case 'importRemoteDump':
                valid = data.remote && data.dump;
                break;
            case 'importLocalDump':
                valid = data.dump;
                break;
            case 'alreadyFixed':
                valid = true;
                break;
            default:
                valid = false;
                break;
        }
        $id('continueButton').disabled = !valid;

    };

    me.start = function (button) {

        $id('importDbMessages').innerHTML = '';
        me.hideProgress();
        me.disableForm();
        me.dumpImportInProgress = false;
        me.removeDump = false;
        me.tables = 'all';
        me.collectFormData();

        switch (me.actionValue) {

            case 'alreadyFixed':
                if (!inst.action('db/isValid', null, null)) {
                    $id('importDbMessages').innerHTML = 'Error: Database is still empty or invalid';
                    me.enableForm();
                } else {
                    //continue installation
                    $id('importDbForm').remove();
                    installer.install();
                    installer.addMessage('done');
                }
                break;

            default:
                typeActions = typesActions[me.actionValue];
                me.action(typeActions[0], 'continue');
        }

    };

    me.continue = function () {

        var nextAction = typeActions[typeActions.indexOf(lastTypeAction) + 1];

        if (nextAction) {
            me.action(nextAction, 'continue');
        } else {
            me.complete();
        }

    };

    me.collectFormData = function () {
        var data = getInputData('importDbForm');
        me.actionValue = data.action;
        me.dump = data.dump;
        me.remoteInstallationName = data.action === 'importLocalDump' ? null : data.remote;
        me.tablesMode = $id('tablesMode').value;
    };

    me.disableForm = function () {

        var elements = getFormElements('importDbForm');
        for (var i = 0; i < elements.length; i++) {
            elements[i].disabled = true;
        }

    };

    me.enableForm = function () {

        var elements = getFormElements('importDbForm');
        for (var i = 0; i < elements.length; i++) {
            elements[i].disabled = false;
        }
        me.validateForm();

    };

    me.importProgress = function () {
        me.action(
            'getProgressHtml',
            function (response) {
                if ($id('importDbProgress') && (me.dumpImportInProgress)) {
                    if (response.html) {
                        me.showProgress();
                        $id('importDbProgress').innerHTML = response.html;
                    }
                    setTimeout(me.importProgress, 500);
                }
            },
            false
        )
    };

    me.showProgress = function () {
        $id('importDbProgressContainer').style.display = 'table-row';
    };

    me.hideProgress = function () {
        $id('importDbProgressContainer').style.display = 'none';
    };

    me.applySelectedTables = function () {
        me.tables = [];
        getInputData('dbTables', false).tables.foreach(function (k, v) {
            if (v) me.tables.push(k);
        });
    };

    me.calculateDumpDataSize = function () {
        me.dumpDataSize = 0;
        if (me.tables !== 'all') {
            me.tables.foreach(function (k, v) {
                me.dumpDataSize += me.dumpInfo.tables[v].dataSize;
            });
        } else {
            me.dumpInfo.tables.foreach(function (k, table) {
                me.dumpDataSize += table['dataSize'];
            });
        }
    };

    me.renderTablesForm = function () {
        $id('importDbMessages').innerHTML += 'evaluating size of tables...';
        me.action('getTablesInfoHtml',
            function (response) {
                if (response.html) {
                    $id('importDbMessages').innerHTML += ' done <br/> Please select tables and continue.';
                    $id('importDbMessages').innerHTML += response.html;
                } else {
                    $id('importDbMessages').innerHTML += ' fail';
                    me.enableForm();
                }
            }
        );
    }

};
