function dbFilterChange(input, event) {
    event = event || window.event;
    if (event.keyCode == 13) {
        inst.action('saveDbFilter', input.value, inst.reloadForm);
    }
}

function switchDatabasePopup(database) {
    inst.popupTemplate(
        'db/switch/popup',
        {database: database},
        switchDatabase
    );
}

function switchDatabase(data) {
    inst.action(
        'db/switch',
        data,
        switchDatabaseResult
    );
}

function switchDatabaseResult(response) {
    inst.popupHtml(response, inst.reloadForm, false);
}

function createDatabase(input, event) {
    event = event || window.event;
    if (event.keyCode == 13) {
        inst.action('db/create', input.value, inst.reloadForm);
    }
}

function dropDatabase(database, confirmNeeded) {
    if (!confirmNeeded
        || (confirmNeeded && confirm('Are you sure you want to delete ' + database + ' database'))
    ) {
        inst.action('db/drop', database, inst.reloadForm);
    }
}

function databaseImport(database) {

    $id('databasesList.import.title').innerHTML = '<b>Import into "' + database + '"</b>:';
    hide($id('databasesList'));
    show($id('databasesList.import'));

    // init import dialog
    $id('importDbAction.fixed').remove();
    dbImport.database = database;
    dbImport.complete = inst.reloadForm;
    dbImport.init();

}

function commit() {

    var _commit = function () {
        inst.action(
            'git/commit',
            {'comment': $id('commitComment').value, 'doPush': $id('doPush').checked},
            function (response) {
                if (response.pushError) alert('Failed to push: ' + response.pushError);
                inst.reloadForm();
            }
        );
    };

    if ($id('commitComment').value.trim() === '') {
        // validation for empty comment is implemented on server side
        _commit();
    }

    if (isCurrentBranchSystem()) {
        new Popup(
            {html: 'Are you sure you need to commit into system branch?'},
            _commit
        );
    } else {
        if (!isMatchedBranchWithComment()) {
            new Popup(
                {html: 'Comment doesn\'t match to branch name. Are you sure?'},
                _commit
            );
        } else {
            _commit();
        }
    }

}

function commitCommentKeyup(input, event) {
    refreshCommitBtnColor();
    event = event || window.event;
    if (event.keyCode == 13 && event.ctrlKey) commit();
}

function refreshCommitBtnColor() {
    var commitBtnColorOk = '#008800';
    var commitBtnColorWarn = '#aa0000';

    var btn = $id('commitBtn');

    if (isCurrentBranchSystem()) {
        btn.style.color = commitBtnColorWarn;
        return;
    }
    btn.style.color = isMatchedBranchWithComment() ? commitBtnColorOk : commitBtnColorWarn;
}

function isCurrentBranchSystem() {
    return (currentBranch === 'master') || currentBranch.match(/^(Alpha|Beta)/);
}

function isMatchedBranchWithComment() {

    var issueCodeRx = /^\s*[0-9A-Z]+\s*-\s*\d+/;
    if (!currentBranch) return false;
    var match = currentBranch.match(issueCodeRx);
    var currentBranchCode = match ? match[0].replace(/\s/g, '') : false;
    if (!currentBranchCode) return true;

    var match = $id('commitComment').value.match(issueCodeRx);
    var commentCode = match ? match[0].replace(/\s/g, '') : false;
    if (!commentCode) return false;

    return (currentBranchCode === commentCode);

}
