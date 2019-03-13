Popup = function (contentSource, onOk, onCancel) {

    // init variables
    var me = this;
    var showCancel = true;

    if ((typeof(onOk) === 'undefined' && typeof(onCancel) === 'undefined') || (onCancel === false)) {
        showCancel = false;
    }

    if (typeof(onOk) === 'undefined') {
        onOk = function () {
        }
    }
    if (typeof(onCancel) === 'undefined') {
        onCancel = function () {
        }
    }

    if (!showCancel) {
        // if cancel button is not shown then popup contain info only and we need to run onOk to update page
        onCancel = onOk;
    }

    // validate arguments
    if (!contentSource.template && (typeof contentSource.html === 'undefined')) {
        throw 'Popup: invalid contentSource';
    }

    var render = function (html) {

        // auto cancel for empty content
        if (isStringEmpty(html)) {
            onCancel();
            return;
        }

        // render
        var cancelBtn = $id('popup.button.cancel');
        $id('popup.content').innerHTML = html;
        $id('popup.button.ok').onclick = ok;
        cancelBtn.onclick = cancel;
        showCancel ? show(cancelBtn) : window.hide(cancelBtn);
        $id('popup-wrapper-table').style.display = 'block';

        $id('popup.button.ok').focus();

        // restore scroll to the top if focus scrolled content to the bottom
        $id('popup').scrollTop = 0;

        document.body.addEventListener('keyup', cancelByEscape);

        evalScripts(html);

        // show horizontal scroll
        var $block = $id('popup.content');
        var $parent = $block.parentNode;
        if ($block.scrollWidth > $parent.clientWidth) {
            $parent.parentNode.className = 'move-button';
        } else {
            $parent.parentNode.className = '';
        }

    };

    var cancel = function () {
        hide();
        onCancel();
    };

    var hide = function () {
        window.hide($id('popup-wrapper-table'));
        document.body.removeEventListener('keyup', cancelByEscape);
    };

    var cancelByEscape = function (event) {
        event = event || window.event;
        if (event.keyCode === 27) {
            cancel();
        }
    };

    var ok = function () {
        if (onOk(getInputData('popup.content')) !== false) {
            hide();
        }
    };

    me.lockOkButton = function (seconds) {

        var okButton = $id('popup.button.ok');

        resetOkLock();
        popupLockOkButtonOriginHtml = okButton.innerHTML;

        okButton.disabled = true;
        okButton.innerHTML = 'OK (' + seconds + ')';
        okButton.lockSecondsLeft = seconds;

        var updateOkButton = function () {
            okButton.lockSecondsLeft--;
            okButton.innerHTML = popupLockOkButtonOriginHtml + ' (' + okButton.lockSecondsLeft + ')';
            if (okButton.lockSecondsLeft <= 0) {
                okButton.innerHTML = popupLockOkButtonOriginHtml;
                okButton.disabled = false;
            } else {
                popupLockOkButtonTimeout = setTimeout(updateOkButton, 1000);
            }
        };

        popupLockOkButtonTimeout = setTimeout(updateOkButton, 1000);

    };

    var resetOkLock = function () {
        var okButton = $id('popup.button.ok');
        clearTimeout(window.popupLockOkButtonTimeout);
        okButton.innerHTML = window.popupLockOkButtonOriginHtml ? window.popupLockOkButtonOriginHtml : okButton.innerHTML;
        okButton.disabled = false;
    };

    // render popup
    resetOkLock();
    if (contentSource.template) {
        template(contentSource.template, contentSource.vars, render);
    } else {
        render(contentSource.html);
    }

};
