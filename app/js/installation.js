currentTab = false;
currentLink = false;

Installation = function (data) {

    var me = this;

    // construct
    data.foreach(function (k, v) {
        me[k] = v;
    });

    this.action = function (action, ARG, onSuccess, block, onError) {
        var isAsync = onSuccess !== null;
        var onActionSuccess = onSuccess;
        if (isAsync) {
            onActionSuccess = function (response) {
                if (typeof onSuccess === 'function') onSuccess(response);
                me.afterOnActionSuccess(action);
            }
        }

        var result = server.action(
            'project/installation/' + action,
            {installation: me, ARG: typeof(ARG) === 'undefined' ? null : ARG},
            onActionSuccess,
            block,
            onError
        );

        if (!isAsync) {
            me.afterOnActionSuccess(action);
        }

        return result;
    }

    this.afterOnActionSuccess = function (action) {
        var onSourceChangeActions = [
            'git/checkout',
            'git/branch/pull',
            'git/branch/update',
            'git/branch/delete',
            'deployment/switchToTag',
            'deployment/pullRemote'
        ];
        if (me.isCloud) {
            onSourceChangeActions.push('deployment/push');
        }
        var onDatabaseChangeActions = ['db/switch'];
        if (onSourceChangeActions.indexOf(action) >= 0) me.onSourcesChange();
        if (onDatabaseChangeActions.indexOf(action) >= 0) me.onDatabaseChange();
    }

    this.onSourcesChange = function () {
        me.refreshDepinsCount();
    }

    this.onDatabaseChange = function () {
        me.refreshDepinsCount();
    }

    this.loadForm = function (form, targetId, clean) {
        clean = clean == null ? true : clean;
        clean && ($id(targetId).innerHTML = '..');
        this.action('getForm', form,
            function (content) {
                $id(targetId).innerHTML = content;
                evalScripts(content);
            }
        );
    }

    this.tabClick = function (tabId) {
        currentTab && ($id('tabsContainer').getElementsByClassName('tab-active')[0].className = 'tab');
        $id('tab.' + tabId).parentNode.className = 'tab-active';
        currentLink = false;
        this.loadForm(currentTab = tabId, 'tabContent');
    }

    this.linkClick = function (linkId, a) {
        var activeLink = $('.left-links a.active');
        activeLink && (activeLink.className = '');
        a && (a.className = 'active');
        currentLink = linkId;
        this.loadForm(currentLink, 'linkContent');
    }

    this.reloadForm = function () {
        var form = currentLink ? currentLink : (currentTab ? currentTab : false)
        form && me.loadForm(form, currentLink ? 'linkContent' : 'tabContent', false);
    }

    this.flushCache = function (options) {
        $id('response').innerHTML = '';
        this.action('mage/flushCaches', options,
            function (response) {
                $id('response').innerHTML = response;
            }
        );
    }

    this.gitCheckoutPopup = function (refType, refName) {
        inst.popupTemplate(
            'git/checkout/popup',
            {
                refType: refType,
                refName: refName,
                isFlushCachePossible: /magento/.test(inst.project.type)
            },
            me.gitCheckout
        );
    }

    this.gitCheckout = function (data) {
        var isLive = /Live/i.test(inst.name);
        if (isLive && !confirm('Are you aware it\'s PRODUCTION?')) {
            return;
        }
        me.action('git/checkout', data, me.gitCheckoutResult);
    }

    this.gitCheckoutResult = function (response) {
        me.popupHtml(response, me.reloadForm, false);
    }

    this.setMaintenance = function () {
        this.action('maintenance/set', $id('mntIps').value, this.reloadForm)
    }

    this.unsetMaintenance = function () {
        this.action('maintenance/unset', null, this.reloadForm)
    }

    this.checkMyIP = function () {
        this.action('getMyIP', null,
            function (ip) {
                $id('myIP').innerHTML = ip;
            }
        );
    }

    this.showDepin = function (file) {
        $id(file).style.display = $id(file).style.display == 'table-cell' ? 'none' : 'table-cell'
    }

    this.doneDepin = function (file) {
        this.action('depins/done', file, function () {
            $id('title_wrap_' + file).remove();
            $id('content_wrap_' + file).remove();
            var depinsCount = ($$('#tabContent .list tr').length - 1) / 2;
            if (depinsCount > 0) {
                $id('depins.count.value').innerHTML = depinsCount;
            } else {
                $id('depins.count').innerHTML = '';
                $id('tabContent').innerHTML = 'No new instructions';
            }
        })
    }

    this.pruneRemoteBranches = function (branches) {
        this.action('git/removeRemoteBranches', branches, this.reloadForm);
    }

    this.pruneLocalBranches = function (branches) {
        this.action('git/removeLocalBranches', branches, this.reloadForm);
    }

    this.fileMouseDown = function () {
        selectionBeforeFileMouseDown = getSelection().toString();
    }

    this.fileClick = function (i) {
        // run click delayed to get correct selection value
        setTimeout(function () {
            var selection = getSelection().toString();
            if (selection !== selectionBeforeFileMouseDown) {
                return;
            }
            showHide($id('diff_' + i));
        }, 0);
    }

    this.resetAll = function () {
        if (confirm('Are you sure you want to reset all changes?')) {
            this.action('git/resetAll', null, this.reloadForm);
        }
    }

    this.resetFiles = function (files, i, event) {
        event = event || window.event;
        if (confirm('Reset "' + files[0] + '"?')) {
            this.action('git/resetFile', files, function () {
                $id('file_' + i).remove();
                $id('diff_' + i).remove();
                if (!$$('.file-diff').length) {
                    $id('git-modifications-result').innerHTML = 'No modifications';
                    hide('#toolbar');
                }
                me.refreshDepinsCount();
            });
        }
        event.stopPropagation();
    }

    this.refreshDepinsCount = function () {
        if (!/magento/.test(this.project.type)) return;
        if (!$id('depins.count')) {
            var container = document.createElement("SPAN");
            container.id = 'depins.count';
            container.isLoading = false;
            with (container.style) {
                minWidth = '20px';
                padding = '0 0 0 5px';
                display = 'inline-block';
            }
            $id('tab.depins').appendChild(container);
        }

        var container = $id('depins.count');

        if (container.isLoading) {
            return;
        }

        container.isLoading = true;
        container.innerHTML = "<img src='/app/skin/icon/loading.gif' />";

        this.action('depins/count', null, function (count) {
            if (count > 0) {
                container.innerHTML = "(<span id='depins.count.value'>" + count + "</span>)";
            } else {
                container.innerHTML = '';
            }
            container.isLoading = false;
        }, false);

    }

    this.createTag = function () {
        this.action('git/tag/create', {name: $id('tagName').value, comment: $id('tagComment').value}, this.reloadForm);
    }

    this.deleteTag = function (tag) {
        if (confirm('Are you sure you want to delete tag "' + tag.name + '"')) {
            this.action('git/tag/delete', tag, this.reloadForm);
        }
    }

    this.editTag = function (tag) {
        var linkContainer = $id('tag_' + tag.name);
        if (linkContainer.innerHtmlBackup) {
            linkContainer.innerHTML = linkContainer.innerHtmlBackup;
            delete linkContainer.innerHtmlBackup;
            return;
        }
        var td = findParent(linkContainer, 'TD');

        var input = document.createElement('input');
        input.value = tag.name;
        input.style.width = (td.clientWidth - 65) + 'px';
        input.style.margin = 0;
        input.onkeyup = function () {
            if (event.keyCode == 13) {
                var newTagName = input.value;
                var ARG = {tag: tag, newName: newTagName};
                me.action('git/tag/rename', ARG, me.reloadForm);
            }
        }

        linkContainer.innerHtmlBackup = linkContainer.innerHTML;
        linkContainer.innerHTML = '';
        linkContainer.appendChild(input);

    }

    this.pruneTags = function (tags) {
        this.action('git/tag/delete', tags, this.reloadForm);
    }


    this.adjustBranchName = function (inputElementId) {
        $id(inputElementId).value = this.action('git/adjustBranchName', $id(inputElementId).value, null);
    }

    this.createBranch = function () {
        this.action('git/branch/create', $id('branchName').value, this.reloadForm);
    }

    this.renameBranchDialog = function (branchName) {
        me.popupTemplate(
            'git/branch/rename/dialog',
            {branchName: branchName},
            me.renameBranch
        );
    }

    this.renameBranch = function (data) {
        me.action('git/branch/rename', data, me.reloadForm);
    }

    this.removeBranch = function (branchName, isEmpty) {
        var confirmed = isEmpty ? true : confirm('Are you sure you want to delete "' + branchName + '" branch?');
        if (!confirmed) return;
        this.action('git/branch/delete', branchName, this.reloadForm);
    }

    this.cherryPick = function () {
        var inputData = getInputData('cherryPickForm', false);
        var selectedHashes = [];
        inputData.hashes.foreach(function (k, v) {
            if (v) selectedHashes.push(k);
        });

        if (!count(selectedHashes)) {
            alert('You did not select commits!');
            return;
        }

        if (!confirm('Are you sure you want to cherry pick select commits?')) {
            return;
        }

        var data = {};
        data['selectedHashes'] = selectedHashes;
        data['dontCommit'] = inputData.dontCommit;

        this.action('git/branch/cherryPick', data, function (response) {
            $id('cherryPickResponse').innerHTML = response;
        });

    }

    this.pushBranchPopup = function (name) {
        var me = this;
        if (me.isBranchStaging(name)) {
            me.popupTemplate('git/branch/push/warning/staging', {}, me.pushBranch);
        } else {
            me.pushBranch();
        }
    }

    this.pushBranch = function () {
        me.action('git/branch/push', null, me.reloadForm);
    }

    this.isBranchStaging = function (name) {
        var preg = new RegExp('^(Alpha|Beta)', 'i');
        return preg.test(name);
    }

    this.foreignKeysFixAll = function () {
        var me = this;
        this.action('db/foreignKeys/fixAll', null, function (response) {
            alert('Updated: ' + response.updated + '\nDeleted: ' + +response.deleted);
            me.reloadForm();
        });
    }

    this.downloadBackup = function (fileName) {

        this.action('uploadRai', null, function (rai) {

            var downloadUrl = rai.url + 'db/backup/download.php?fileName=' + encodeURIComponent(fileName);

            var form = createForm({
                'PWD': rai.PWD,
                'downloadUrl': downloadUrl
            });

            form.setAttribute('action', downloadUrl);

            // hide & add form into DOM because Firefox will not submit it
            form.style.display = 'none';
            document.body.appendChild(form);

            form.submit();

        });

    }

    this.removeBackup = function (file) {
        if (confirm('Are you sure you want to delete "' + file + '" backup?')) {
            this.action('db/backup/remove', {fileName: file}, this.reloadForm)
        }
    }

    this.getBackupInfo = function (file) {
        this.action('db/backup/getInfo', {fileName: file}, me.popupHtml)
    }

    this.refreshLinks = function () {
        this.action('refreshLinks', null, this.reloadForm);
    }

    this.fixRights = function (options) {
        var allow = true;
        if (options.fixes !== undefined && options.fixes['media/catalog']) {
            if (!confirm('Fix media/catalog rights may increase HDD load for a while. Proceed anyway?')) {
                allow = false;
            }
        }
        if (allow) {
            $id('fixRights').innerHTML = "";
            this.action('mage/fixRights', options,
                function (response) {
                    $id('fixRights').innerHTML = "Rights have been fixed";
                }
            );
        }
    }


    this.fixM2Rights = function () {
        var result = $id('fixRights');
        result.innerHTML = '';
        inst.action('m2/fixRights', null, function (response) {
            result.innerHTML = "Rights have been fixed";
        });
    }

    this.importAttributesDialog = function () {
        me.popupHtml('Make sure you backed database up, proceed?', me.importAttributes);
    }

    this.importAttributes = function () {
        $id('import_btn').disabled = true;
        me.action('mage/attributes/import', null,
            function (response) {
                $id('response').innerHTML = response;
                $id('import_btn').disabled = false;
            }
        );
    }

    this.showAllIssues = function () {
        hide($id('showAllIssues'));
        this.action('git/changelist/showAll', null, function (response) {
            $id('changelist-content').innerHTML = response;
        });
    }

    this.autoLogin = function (boLink) {
        var boPath = boLink.replace(/https?:\/\/[^/]+\//, '');
        var boUrl = boLink.replace(boPath, '');

        this.action('uploadRai', null, function (rai) {

            var raiPath = rai.url.replace(/https?:\/\/[^/]+\//, '');
            var autoLoginUrl = boUrl + raiPath + 'autologin/bo.php';

            var form = createForm({
                'PWD': rai.PWD,
                'boPath': boPath
            });

            form.setAttribute('action', autoLoginUrl);
            form.setAttribute('target', '_top');

            // hide & add form into DOM because Firefox will not submit it
            form.style.display = 'none';
            document.body.appendChild(form);

            form.submit();

        });

    }

    this.dbSearch = function () {
        this.action('db/search', getInputData('dbSearchForm'),
            function (response) {
                $id('dbSearchResult').innerHTML = response;
            }
        );
    }

    this.findFile = function () {
        this.action('findFile', getInputData('fileSearchForm'),
            function (response) {
                $id('fileSearchResult').innerHTML = response;
            }
        );
    }

    this.rotateLogs = function () {
        var result = $id('log.rotate.result');
        result.innerHTML = '';
        inst.action('log/rotate', null, function (response) {
            result.innerHTML = response;
        });
    }

    this.compileScss = function () {
        var result = $id('scss.compile.result');
        result.innerHTML = '';
        inst.action('scss/compile', null, function (response) {
            result.innerHTML = response;
        });
    }

    this.getInfo = function () {
        me.action('mage/refreshInfo', null, me.reloadForm);
    }

    this.popup = function (contentSource, onOk, onCancel) {
        if (contentSource.template) {
            contentSource.template = 'project/installation/forms/default/' + contentSource.template;
            contentSource.vars.inst = me;
        }
        popup = new Popup(contentSource, onOk, onCancel);
    }

    this.popupHtml = function (html, onOk, onCancel) {
        me.popup({html: html}, onOk, onCancel);
    }

    this.popupTemplate = function (templateName, templateVars, onOk, onCancel) {
        me.popup({template: templateName, vars: templateVars}, onOk, onCancel);
    }

    me.onFeAutoLogInClick = function (feLink) {
        me.popupTemplate('links/autologin/fe', {}, function (data) {
            location.href = '/?view=magento/autologin/fe'
                + '&source=' + encodeURIComponent(me.source)
                + '&project=' + encodeURIComponent(me.project.name)
                + '&name=' + encodeURIComponent(me.name)
                + '&email=' + encodeURIComponent(data.email)
                + '&feUrl=' + encodeURIComponent(feLink);
        });
    }

    this.backupConfig = function () {
        var result = $id('config.backup.result');
        result.innerHTML = '';
        inst.action('config/backup', getInputData('configBackupForm'), function (response) {
            result.innerHTML = response;
        });
    }

    this.validateConfigBackupForm = function () {
        var data = getInputData('configBackupForm');
        var valid = data.comment;
        $id('backupButton').disabled = !valid;
    }

    this.loadModifications = function () {
        me.action('git/modifications/getHtml', getInputData('modificationsForm'),
            function (response) {
                $id('git-modifications-result').innerHTML = response;
                evalScripts(response);
            });
    }

    // can be used in installer only
    this.setCustomMainDomain = function (domain) {
        me.action(
            'setCustomMainDomain',
            $id('customMainDomain').value,
            function (response) {
                $id('customMainDomainForm').remove();
                installer.addMessage('done');
                installer.install();
            }
        );
    }

    this.pullBranch = function () {
        me.action(
            'git/branch/pull',
            null,
            function (response) {
                me.popupHtml(response, me.reloadForm, false);
            }
        );
    };

    this.updateBranch = function (currentBranch) {
        me.action(
            'git/branch/update',
            {currentBranch: currentBranch},
            function (response) {
                me.popupHtml(response, me.reloadForm, false);
            }
        );
    }

};

Backup = function () {

    var me = this;

    this.onBackupModeChange = function (mode) {
        var button = $id('createBackupBtn');
        if (mode == 'selected') {
            button.innerHTML = 'Continue';
            button.setAttribute('onclick', 'backup.loadTablesInfo()');
        } else {
            button.innerHTML = 'Create';
            button.setAttribute('onclick', 'backup.create()');
        }
    }

    this.loadTablesInfo = function () {
        if ($id('tablesMode').value == 'selected') {
            $id('tablesInfo').innerHTML += 'analyzing database...';
            inst.action('db/getTablesInfoHtml', {},
                function (html) {
                    if (html) {
                        $id('tablesInfo').innerHTML += ' done <br/> Please select tables and continue.';
                        $id('tablesInfo').innerHTML += html;
                    } else {
                        $id('tablesInfo').innerHTML += ' fail';
                    }
                }
            );
        } else {
            me.create();
        }
        $('#createBackupBtn').style.display = 'none';
    }

    this.create = function () {
        me.name = $id('backupName').value;
        me.singleTransaction = $id('singleTransaction').checked;
        me.isInProgress = false;
        if ($id('tablesMode').value == 'all') {
            me.tables = 'all';
        } else {
            if ($$('.dbTableCheckbox:checked').length < 1) {
                alert('Please select tables.');
                return;
            }

            me.tables = getInputData('dbTables', false).tables;
        }
        me.isInProgress = true;
        inst.action(
            'db/backup/create',
            {
                fileName: me.name,
                singleTransaction: me.singleTransaction,
                tables: me.tables
            },
            function (response) {
                me.isInProgress = false;
                if (response.wereErrors) {
                    alert('There were errors during mysqldump, please check file "' + me.name + '.sql.gz.errors"');
                }
                inst.reloadForm();
            },
            true,
            function (error) {
                me.isInProgress = false;
                server.onActionError(error);
            }
        );
        me.getProgress();
    }

    this.getProgress = function () {
        inst.action('db/backup/getProgressHtml', {fileName: me.name, tables: me.tables}, function (response) {
            if (!me.isInProgress) return;
            $id('backupProgress').innerHTML = response;
            setTimeout(me.getProgress, 2000);
        }, false)
    }

}

BranchesHighlight = function (targetElements) {

    var me = this;
    var issueCodesInTextRx = /[0-9a-z]+-\d+/ig;
    var wasClicked = false;

    var getTextEl = function () {
        return $id('branchesHighlight.text');
    }

    me.onButtonClick = function () {
        showHide(getTextEl());
    }

    me.onTextKeyUp = function () {
        if (event.keyCode === 27) {
            hide(getTextEl());
            return;
        }
        me.run();
    }

    me.onTextChange = function () {
        me.run();
    }

    me.onTextFocus = function () {
        if (!wasClicked) {
            getTextEl().value = '';
        }
        wasClicked = true;
    }

    me.run = function () {
        var codes = getTextEl().value.match(issueCodesInTextRx);
        var elements = $$(targetElements);
        elements.foreach(function (i, el) {
            if (!el.origText) el.origText = el.innerHTML;
            var innerHTML = html2text(el.origText);
            if (codes) {
                codes.foreach(function (i, code) {
                    var match = innerHTML.match(new RegExp(code, 'i'));
                    if (!match) return;
                    innerHTML = innerHTML.replace(match[0], '<span style="background-color: #5bff61">' + match + '</span>');
                })
            }
            el.innerHTML = innerHTML;
        });
    }

}

CodeReview = function () {

    var me = this;

    me.branchClick = function (i) {
        showHide($id('branch_diff_' + i));
    };

    me.compareBranches = function () {
        inst.action(
            'git/codeReview/getHtml',
            getInputData('codeReviewForm'),
            function (html) {
                $id('codeReviewResult').innerHTML = html;
            })
    };

    me.updateBranchDiffDisplay = function (branchIdx, display) {
        if (display === 'block') {
            show('#branch_diff_' + branchIdx);
        }
        updateAll('#branch_diff_' + branchIdx + ' .file-diff', 'style.display', display);
    };

    me.selectDeselectAll = function () {
        var branches = $$('#codeReviewForm input[type="checkbox"]:not([id=ignoreSpaces])');
        branches.foreach(function (i, e) {
            e.checked = $('#selectAll').checked;
        })
    };

}
