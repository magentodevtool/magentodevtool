Deployment = function (data) {

    // default values
    this.currentRemoteBranch = false;
    this.remoteHashBeforeDeployment = false;
    this.branchesToSelect = {};
    this.branchesToDeploy = [];
    this.resetEnvironment = false;
    this.unmergedFiles = []; // used to check if conflicts resolving was correct
    this.type = null;
    this.newTagName = '';
    this.newTagComment = '';
    this.isConfirmed = false;
    this.lockHash = null;
    this.formHash = null;
    this.mergeHash = null;
    this.mageVersion = null;
    this.mage2 = {
        'mode': null,
        'doCompileDi': false,
        'doCompileStaticContent': false,
        'cloud': null
    };

    var me = this;
    var issueCodeInBranchRx = /^[0-9a-z]+-\d+/i;

    // apply data
    data.foreach(function (k, v) {
        me[k] = v;
    });

    var isCloud = this.mage2.cloud !== null;

    var actions = {
        'validateForm': 'validating..',
        'lock/capture': 'locking..',
        'lock/recapture': 'recapturing the lock..',
        'localInstallation/prepareRepo': 'preparing local installation repository..',
        'localInstallation/checkRepoUrl': 'checking local installation repository URL..',
        'localInstallation/m2/configure': 'configure local installation..',
        'localInstallation/m2/db/setCred': 'setting local installation database credentials..',
        'localInstallation/m2/db/prepare': 'preparing local installation database..',
        'localInstallation/m2/db/update': 'updating local installation database..',
        'validate': 'validating..',
        'saveRemoteHash': 'saving remote hash..',
        'checkBeforeDepins': 'checking \'before\' depins..',
        'merge': 'merging..',
        'checkDeploymentCompatibility': 'check deployment compatibility..',
        'generation/scss/run': 'compiling scss..',
        'generation/composer/run': 'composing modules..',
        'updateLiveBranch': 'updating Live branch..',
        'updateProductionBranch': 'updating production branch..',
        'push': 'pushing..',
        'm2/compilation/prepare': 'preparing local magento for compilations..',
        'm2/compilation/configDump/update': 'updating config dump from remote..',
        'm2/compilation/run': 'compiling di and(or) static content..',
        'm2/compilation/configDump/reset': 'resetting local config dump..',
        'm2/compilation/staticContent/optimized/populate': 'populating static content locales..',
        'm2/compilation/staticContent/optimized/generateJsonTranslates': 'generating js-translation.json files..',
        'm2/compilation/commit': 'committing di and static content..',
        'manualSteps/show': 'manual steps..',
        'manualSteps/fetch': 'fetching manual steps..',
        'pullRemote': 'pulling on remote..',
        'upgradeModules': 'upgrade modules..',
        'cleanGeneration': 'cleaning generation..',
        'resetModifications': 'resetting modifications..',
        'abortMerge': 'aborting merge..',
        'commitMerge': 'committing merge..',
        'fixRemoteRights': 'fixing rights on remote..',
        'createTag': 'creating a tag..',
        'switchToTag': 'switching to the tag..',
        'newrelic/sendDeployment': 'sending deployment to new relic..',
        'getDeployedChanges': 'load deployed changes..',
        'rotateLogs': 'rotate logs..',
        'backupConfig': 'backup config..',
        'lock/release': 'unlocking..'
    };
    var retryRedirects = {
        'm2/compilation/run': 'localInstallation/m2/db/prepare'
    };

    var typesActions;
    var typeActions;
    var lastTypeAction;

    this.start = function () {
        this.isConfirmed = false;
        this.collectFormData();
        this.generateTypesActions();
        typeActions = typesActions[this.type];
        // fire steps.. run first one
        this.action(typeActions[0], 'continue');
    };

    this.collectFormData = function () {
        var data = getInputData('deploymentForm');
        var branchesToDeploy = [];
        data.branches.foreach(function (branchName, toDeploy) {
            if (toDeploy) branchesToDeploy.push(branchName);
        });
        this.branchesToDeploy = branchesToDeploy;
        this.resetEnvironment = !!data.resetEnvironment; // - convert "undefined" to bool
        if (this.type === 'production') {
            this.newTagName = data.newTagName.trim();
            this.newTagComment = data.newTagComment.trim();
        }
        this.mage2.doCompileDi = data.doCompileDi;
        this.mage2.doCompileStaticContent = data.doCompileStaticContent;
        this.mage2.staticContentCompilationType = data.staticContentCompilationType;
    };

    this.generateTypesActions = function () {

        if (isCloud) {
            return me.generateTypesActionsCloud();
        }

        var isM2CompilationRequired =
            inst.project.type === 'magento2'
            && me.mage2.mode === 'production'
            && (me.mage2.doCompileDi || me.mage2.doCompileStaticContent);

        var prepareLocalInstallationForM2CompilationActions = [
            'localInstallation/m2/configure'
        ];
        if (me.mageVersion < '2.2') {
            prepareLocalInstallationForM2CompilationActions =
                prepareLocalInstallationForM2CompilationActions.concat([
                    'localInstallation/m2/db/prepare',
                    'localInstallation/m2/db/update'
                ]);
        }

        var m2CompilationAction = [
            'm2/compilation/prepare'
        ];
        if (me.mageVersion >= '2.2') {
            m2CompilationAction = m2CompilationAction.concat([
                'm2/compilation/configDump/update'
            ]);
        }
        m2CompilationAction = m2CompilationAction.concat([
            'm2/compilation/run'
        ]);
        if (me.mageVersion < '2.2') {
            m2CompilationAction = m2CompilationAction.concat([
                'm2/compilation/staticContent/optimized/populate',
                'm2/compilation/staticContent/optimized/generateJsonTranslates'
            ]);
        } else {
            m2CompilationAction = m2CompilationAction.concat([
                'm2/compilation/configDump/reset'
            ]);
        }
        m2CompilationAction = m2CompilationAction.concat([
            'm2/compilation/commit'
        ]);

        var initialActions = [
            'validateForm',
            'localInstallation/prepareRepo',
            'localInstallation/checkRepoUrl',
            'lock/capture',
            'saveRemoteHash' // should be right after lock/capture as remote hash is needed for lock/recapture
        ];

        typesActions = {
            'staging': [].concat(
                initialActions,
                isM2CompilationRequired ? prepareLocalInstallationForM2CompilationActions : [],
                [
                    'validate',
                    'checkBeforeDepins',
                    'merge',
                    'checkDeploymentCompatibility',
                    'generation/scss/run',
                    'generation/composer/run',
                    'push'
                ],
                isM2CompilationRequired ? m2CompilationAction : [],
                [
                    'manualSteps/show',
                    'pullRemote',
                    'cleanGeneration',
                    'upgradeModules',
                    'rotateLogs',
                    'backupConfig',
                    'newrelic/sendDeployment'
                ]
            ),
            'production': [].concat(
                initialActions,
                isM2CompilationRequired ? prepareLocalInstallationForM2CompilationActions : [],
                [
                    'validate',
                    'checkBeforeDepins',
                    'merge',
                    'updateLiveBranch',
                    'checkDeploymentCompatibility',
                    'generation/scss/run',
                    'generation/composer/run',
                    'push'
                ],
                isM2CompilationRequired ? m2CompilationAction : [],
                [
                    'manualSteps/show',
                    'manualSteps/fetch',
                    'createTag',
                    'switchToTag',
                    'cleanGeneration',
                    'upgradeModules',
                    'rotateLogs',
                    'backupConfig',
                    'newrelic/sendDeployment'
                ]
            )
        };

    };

    this.generateTypesActionsCloud = function () {
        var initialActions = [
            'validateForm',
            'localInstallation/prepareRepo',
            'localInstallation/checkRepoUrl',
            'lock/capture',
            'saveRemoteHash', // should be right after lock/capture as remote hash is needed for lock/recapture
            'validate',
            'checkBeforeDepins',
            'merge'
        ];
        typesActions = {
            'staging': [].concat(
                initialActions,
                [
                    'push',
                    'rotateLogs',
                    'newrelic/sendDeployment'
                ]
            ),
            'production': [].concat(
                initialActions,
                [
                    'updateProductionBranch',
                    'createTag',
                    'push',
                    'rotateLogs',
                    'newrelic/sendDeployment'
                ]
            )
        };
    };

    this.action = function (action, onSuccess, block) {

        block = block == null ? true : block;
        var isRequestBackground = !block;

        if (!isRequestBackground) {
            me.mainAction(action, onSuccess);
        } else {
            me.backgroundAction(action, onSuccess);
        }

    };

    this.mainAction = function (action, onSuccess) {
        me.setStatus(actions[action]);
        inst.action(
            'deployment/' + action,
            me,
            function (response) {
                me.setStatus('')
                if (response === 1) {
                    onSuccess && me[onSuccess]();
                } else {
                    me.showDialog(response.message);
                }
            },
            true,
            this.actionError
        );
        if (typeActions.indexOf(action) !== -1) lastTypeAction = action;
    };

    this.backgroundAction = function (action, onSuccess) {
        inst.action('deployment/' + action, me, onSuccess, false);
    };

    this.actionError = function (error) {
        server.onActionError(error);
        $id('deploymentFormStatus').innerHTML = '';
    };

    this.setStatus = function (status) {
        if ($id('deploymentForm').style.display !== 'none') {
            $id('deploymentFormStatus').innerHTML = status;
            return;
        }
        this.disableButtons();
        if ($id('deployment.status')) $id('deployment.status').remove();
        if (status !== '') {
            $id('deploymentProgress').innerHTML += '<div id="deployment.status"></div>';
            $id('deployment.status').innerHTML = status;
        }
    };

    this.getLastActionLabel = function () {
        return actions[lastTypeAction];
    }

    this.disableButtons = function () {
        var buttons = $$('#deploymentProgress button');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = true;
            buttons[i].setAttribute('id', '');
        }
    };

    this.showDialog = function (dialog) {
        $id('deploymentProgress').innerHTML += dialog;
        evalScripts(dialog);
    };

    this.retry = function () {
        var retryAction = lastTypeAction;
        if (retryRedirects[retryAction] !== undefined) {
            retryAction = retryRedirects[retryAction];
        }
        this.action(retryAction, 'continue');
    };

    this.continue = function () {
        var nextAction = typeActions[typeActions.indexOf(lastTypeAction) + 1];
        nextAction ? this.action(nextAction, 'continue') : this.end();
    };

    this.end = function () {
        $id('deploymentProgress').innerHTML += '<br><span style="color:green">Deployment has been finished successfully.</span>';
        me.releaseLock();
        me.action('getDeployedChanges');
    };

    this.abort = function () {
        me.abortEnd();
    };

    this.abortEnd = function () {
        $id('deploymentProgress').innerHTML += '<br><span style="color:red">Deployment has been aborted</span>';
        me.releaseLock();
    };

    this.generateNewTagCommentOnOff = function () {
        $id('deployment.newTagComment').readOnly = $id('deployment.generateNewTagComment').checked;
        this.generateNewTagComment();
    };

    this.generateNewTagComment = function () {
        if (!$id('deployment.newTagComment')) return;
        if (!$id('deployment.generateNewTagComment').checked) return;
        this.collectFormData();
        var keywords = [];
        this.branchesToDeploy.foreach(function (k, v) {
            var matches = issueCodeInBranchRx.exec(v);
            var keyword = matches ? matches[0] : v;
            keywords.push(keyword);
        });
        $id('deployment.newTagComment').value = keywords.join(', ');
    };

    this.updateLock = function () {

        var form = $id('deploymentForm');

        if (!me.formHash) {
            me.formHash = form.hash;
        }

        if (form === null || form.hash !== me.formHash) {
            me.releaseLock();
            return;
        }

        me.action('lock/prolong', function (success) {
            if (!success) return;
            me.updateLockTimeout = setTimeout(me.updateLock, 5000);
        }, false);

    };

    this.releaseLock = function () {
        clearTimeout(me.updateLockTimeout);
        me.action('lock/release', false, false);
    };

    this.markCustomNotesAsRead = function () {
        me.showHideCustomNotes();
        me.action('customNotes/markAsRead', false, false);
    };

    this.showHideCustomNotes = function () {
        showHide('#deployment-custom-notes, #deployment-custom-notes-show');
    };

    this.showHideCompilationType = function () {
        showHide('#staticContentCompilationType');
    };

    // only for debug by F12
    this.getPlan = function () {
        this.collectFormData();
        me.generateTypesActions();
        typeActions = typesActions[this.type];
        return typeActions;
    }

};

function applyCustomOldMaintenance() {
    var config = $id('customOldMaintenance').value;
    inst.action('maintenance/custom/old/apply', config, inst.reloadForm);
    $id('customOldMaintenanceForm').innerHTML = 'applying..'
}
