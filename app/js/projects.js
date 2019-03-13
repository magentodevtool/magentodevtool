function addProject() {
    $id('addProjectForm').style.display = $id('addProjectForm').style.display == 'block' ? 'none' : 'block';
}

function projectSave() {
    server.action(
        'project/save',
        getInputData('addProjectForm'),
        null
    );
    location.reload();
}

function addInstallation() {
    $id('addInstallationForm').style.display = $id('addInstallationForm').style.display == 'block' ? 'none' : 'block';
}


function installationTypeChange() {
    $id('installationRemoteParams').style.display = $id('installationType').value == 'remote' ? 'table-row-group' : 'none';
}

function installationSave() {
    server.action(
        'project/addInstallation',
        getInputData('addInstallationForm'),
        null
    );
    location.reload();
}

function installationProjectChange() {
    var projectName = $id('installationProject').value;
    hide($id('installationProjectType.magento1'));

    if (projectsTypes[projectName] === 'magento1') {
        show($id('installationProjectType.magento1'));
        show($id('installationLocalParams'));
    } else {
        hide($id('installationLocalParams'));
    }
}

function setShowActivity(value) {
    setPersistentVariable('showActivity', value);
    applyActivity();
}

function applyActivity() {
    var showActivity = getPersistentVariable('showActivity');
    if (showActivity === null) showActivity = true;
    $id('showActivity').checked = showActivity;
    $$('.installation-activity').foreach(
        function (k, v) {
            if (!v.style) return;
            v.style.visibility = showActivity ? 'visible' : 'hidden';
        }
    );
}

function onProjectTypeChange() {
    var docRootDisplay = $id('project.form.type').value === 'simple' ? 'none' : 'table-row';
    $id('project.form.docRoot').style.display = docRootDisplay;
}

projects = {}
projects.remote = {}
projects.remote.options = {}

projects.remote.synchronize = function () {
    server.action(
        'projects/remote/synchronize',
        null,
        function () {
            location.reload();
        }
    );
}

projects.remote.renderStatus = function () {
    var container = $id('synchronize.container');
    container.innerHTML = "<img src='/app/skin/icon/loading.gif' />";

    server.action('projects/remote/getStatusHtml', null, function (result) {
        container.innerHTML = result;
    }, false);
}

projects.remote.options.edit = function () {
    hide($id('projects.remote.list'));
    show($id('projects.remote.options.form'));
}

projects.remote.options.save = function () {
    server.action(
        'projects/remote/options/save',
        getInputData('projects.remote.options.form'),
        function () {
            location.reload()
        },
        false
    );
}
