Rsync = {

    'init': function (folder) {
        Rsync.refreshConfig();
        Rsync.refreshRemoteInstallations();
        Rsync.rootDir = folder;
    },

    'remote': null,
    'folders': null,
    'mediaImportInProgress': false,

    'refreshConfig': function () {
        Rsync.dumpImportInProgress = false;
        Rsync.remote = null;
        Rsync.folders = null;
    },

    'refreshRemoteInstallations': function (list) {

        $id('remoteMediaFolder').style.display = 'none';

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

        Rsync.validateForm();

    },

    'remoteInstallationChange': function (select) {
        if (select.value === '0') {
            $id('remoteMediaFolder').style.display = 'none';
        } else {
            Rsync.refreshRemoteMedia();
        }
    },

    'refreshRemoteMedia': function () {

        inst.action('rsync/media/getItemsHtml', $id('remoteInstallation').value, function (html) {

            $id('remoteMediaFolder').innerHTML = html;
            $id('importMediaMessages').innerHTML = '';
            $id('checkAll').checked = false;
            $id('remoteMediaFolder').style.display = 'table-row';

            Rsync.validateForm();
            Rsync.remote = $id('remoteInstallation').value;

        });
    },

    'getDirFullSize': function (folder) {
        inst.action(
            'getDirSize',
            {
                remote: Rsync.remote,
                folder: folder,
                rootDir: Rsync.rootDir,
                excludeRegexp: "media/catalog/product/cache"
            },
            function (response) {
                if (response) {
                    $id(folder).innerHTML = response;
                    hide($id('fullSizeFor' + folder));
                }
            }
        );
    },

    'validateForm': function () {

        var data = getInputData('importMediaForm');
        var valid = false;
        valid = data.remote && data.folders;
        $id('continueButton').disabled = !valid;

    },

    'continue': function () {

        var formId = 'importMediaForm';
        var data = getInputData(formId);
        Rsync.folders = Rsync.getMediaFoldersList(data);

        if (!count(Rsync.folders)) {
            $id('importMediaMessages').innerHTML = 'Please, select some folders';
            return false;
        }

        Rsync.resetProgress();
        Rsync.disableForm();
        Rsync.mediaImportInProgress = true;

        inst.action(
            'rsync/run',
            {remote: Rsync.remote, srcFolders: Rsync.folders, destFolder: Rsync.rootDir},
            function (response) {
                if (response) {
                    inst.action('mage/fixRights', {
                        mode: 'specific',
                        type: 'local',
                        fixes: {'media': true, 'media/catalog': true}
                    }, null);
                    Rsync.mediaImportInProgress = false;
                    Rsync.finishImport();
                }
            }
        );
        $id('importMediaMessages').innerHTML += '<img src="/app/skin/icon/loading.gif" style="margin: -2px 5px 0 0"/>scanning for new files..';
        Rsync.importProgress();
        return true;
    },

    'getMediaFoldersList': function (data) {
        var mediaFoldersList = [];
        data.folders.foreach(function (folder, checked) {
            if (checked) mediaFoldersList.push(folder);
        });
        return mediaFoldersList;
    },

    'disableForm': function () {

        var elements = getFormElements('importMediaForm');
        for (var i = 0; i < elements.length; i++) {
            elements[i].disabled = true;
        }

    },

    'enableForm': function () {

        var elements = getFormElements('importMediaForm');
        for (var i = 0; i < elements.length; i++) {
            elements[i].disabled = false;
        }
        Rsync.validateForm();

    },

    'importProgress': function () {
        inst.action('rsync/getProgress', {folder: Rsync.rootDir},
            function (percent) {
                if ($id('importMediaProgress') && Rsync.mediaImportInProgress) {
                    if (percent) {
                        Rsync.showProgress();
                        $id('importMediaMessages').innerHTML = '';
                        $id('importMediaProgress').innerHTML = Rsync.progressHtml(percent);
                    }
                    setTimeout(function () {
                        Rsync.importProgress();
                    }, 500);
                }
            },
            false
        );
    },

    'showProgress': function () {
        $id('importMediaProgressContainer').style.display = 'table-row';
    },

    'resetProgress': function () {
        $id('importMediaMessages').innerHTML = '';
        $id('importMediaProgress').innerHTML = Rsync.progressHtml(0);
        hide('#importMediaProgressContainer');
    },

    'progressHtml': function (percent) {
        return '<div class="progressbar"><div class="progress" style="width:' + percent + '%"><span>' + Math.round(percent) + '%</span></div></div>';
    },

    'finishImport': function () {
        Rsync.resetProgress();
        $id('importMediaMessages').innerHTML = 'Done';
        Rsync.init(Rsync.rootDir);
        Rsync.enableForm();
    }
}