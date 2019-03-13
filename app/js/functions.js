function $id(id) {
    return document.getElementById(id);
}

function $(elements, scope) {
    // if elements is a string then it's selector
    if (getClassName(elements) === 'String') {
        if (!scope) scope = document;
        return scope.querySelector(elements);
    }
    return elements;
}

function $$(elements, scope) {
    // if elements is a string then it's selector
    if (getClassName(elements) === 'String') {
        if (!scope) scope = document;
        elements = scope.querySelectorAll(elements);
    }

    if (getClassName(elements) === 'NodeList') {
        var nodeList = elements;
        elements = [];
        for (var i = 0; i < nodeList.length; i++) elements.push(nodeList[i]);
    }

    // if single element received
    if (getClassName(elements) !== 'Array') elements = [elements];

    return elements;
}

function $last(elements, scope) {
    var nodes = $$(elements, scope);
    if (!nodes.length) return null;
    return nodes[nodes.length - 1];
}

function getClassName(object) {
    if (object === null) return null;
    var name = object.constructor.name;
    if (name) return name;

    // firefox %(
    return object.constructor.toString().match(/function\s+(\w*)/)[1];
}

function updateAll(selector, property, value) {
    var elements = $$(selector);
    property = property.split('.');
    for (var i = 0; i < elements.length; i++) {
        var dest = elements[i];
        for (var j = 0; j < property.length - 1; j++) dest = dest[property[j]];
        dest[property[j]] = value;
    }
}

function go(href) {
    document.location.href = href;
}

server = {

    'action': function (action, ARG, onSuccess, block, onError) {

        ARG = typeof(ARG) === 'undefined' ? null : ARG;

        // keep async if false, only null will be sync request
        if (onSuccess === false || typeof onSuccess === 'undefined') {
            onSuccess = function () {
            };
        }

        block = block == null ? true : block;

        if (block && server.userBlocked) {
            throwError('Can\'t send new top level request for "' + action + '" until no response from previous', 'info');
        }

        if (typeof onError !== 'function') onError = server.onActionError;

        var xmlhttp = new XMLHttpRequest();

        xmlhttp.open('POST', '/?action=' + action, onSuccess ? true : false);

        var getResult = function () {

            try {
                var response = JSON.parse(xmlhttp.responseText);
            } catch (e) {
                server.unblockUser(block);
                setTimeout(function () {
                    console.log('Invalid response for action ' + action + ' (full version): "' + xmlhttp.responseText + '"')
                }, 1);
                throw 'Exception: Invalid JSON received for action ' + action;
            }

            if (!response) {
                throw 'Exception: Invalid response received for action ' + action;
            }

            if (response.output) {
                if (response.exception) {
                    console.info('PHP output buffer on "' + action + '" before exception has been thrown: ' + response.output);
                } else {
                    console.warn('PHP output buffer on "' + action + '": ' + response.output);
                }
            }

            if (response.exception) {
                onError(response.exception);
                server.unblockUser(block);
                throwError('Script execution has been terminated because of non-success ajax action', 'info');
            }

            return response.return;

        }

        if (onSuccess) {

            xmlhttp.onreadystatechange = function () {
                if (xmlhttp.readyState == 4) {
                    if (xmlhttp.status == 200) {
                        server.unblockUser(block);
                        onSuccess(getResult());
                    } else {
                        server.unblockUser(block);
                        if (xmlhttp.status !== 0) { // 0 - unload in chrome (if press F5 when ajax request)
                            onError({type: 'text', message: 'Devtool is offline. HTTP status = ' + xmlhttp.status});
                        }
                    }
                }
            }

            server.blockUser(block)

        }

        xmlhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xmlhttp.send('ARG=' + encodeURIComponent(JSON.stringify(ARG)) + '&underground=' + (block ? 0 : 1));

        if (!onSuccess) {
            server.unblockUser(block);
            return getResult();
        }

    },

    'onActionError': function (error) {
        if (error.type === 'text') {
            new Popup({
                html: '<span style="white-space: pre">' + html2text(error.message).replace(/\n/g, '<br>') + '</span>'
            });
            return;
        }
        if (error.type === 'html') {
            new Popup({html: error.message});
            return;
        }
        throwError('Unknown exception type "' + error.type + '"');
    },

    'userBlocked': false,

    'blockUser': function (block) {
        if (block) {
            server.userBlocked = true;
            var cover = $id('cover');
            cover.style.display = 'block'
            if (cover.endProgress) {
                clearTimeout(cover.endProgress);
                delete cover.endProgress;
                return;
            }
            cover.startProgress = setTimeout(function () {
                $id('ajax-progress') && $id('ajax-progress').setAttribute('class', 'ajax-progress-active');
                favicon.setStatusInProgress();
                delete cover.startProgress;
            }, 500)
        }
    },

    'unblockUser': function (block) {
        if (block) {
            server.userBlocked = false;
            var cover = $id('cover');
            cover.style.display = 'none'
            if (cover.startProgress) {
                clearTimeout(cover.startProgress)
                delete cover.startProgress;
                return;
            }
            cover.endProgress = setTimeout(function () {
                $id('ajax-progress') && $id('ajax-progress').setAttribute('class', '');
                favicon.setStatusCalm();
                delete cover.endProgress;
            }, 10);
        }
    },

    'getFile': function (file) {
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.open('GET', file, false);
        xmlhttp.send();
        if (xmlhttp.status === 200) return xmlhttp.responseText;
        return false;
    }

};

Favicon = function () {

    var me = this;

    me.setStatusCalm = function () {
        setStatusFromCanvasContext(function (canvasContext) {
            with (canvasContext) {
                globalAlpha = 0.7;
                fillRect(2, 2, 12, 12);
            }
        });
    };

    me.setStatusInProgress = function () {
        setStatusFromCanvasContext(function (canvasContext) {
            with (canvasContext) {
                fillRect(9, 9, 6, 6);
                fillRect(1, 9, 6, 6);
                fillRect(9, 1, 6, 6);
                fillRect(1, 1, 6, 6);
            }
        });
    };

    var setStatusFromCanvasContext = function (renderCallback) {
        var canvasContext = getCanvasContext();
        canvasContext.clearRect(0, 0, 16, 16);
        renderCallback(canvasContext);
        getElement().setAttribute('href', canvasContext.canvas.toDataURL());
    };

    var getCanvasContext = function () {
        var canvas = document.createElement('canvas');
        canvas.setAttribute('width', '16');
        canvas.setAttribute('height', '16');
        var context = canvas.getContext('2d');
        with (context) {
            globalAlpha = 1;
            fillStyle = getEnvColor();
            lineWidth = 0;
            shadowColor = '#999';
            shadowBlur = 1;
            shadowOffsetX = 1;
            shadowOffsetY = 1;
        }
        return context;
    };

    var getElement = function () {
        var favicon = $id('favicon');
        if (!favicon) {
            var favicon = document.createElement('link');
            favicon.setAttribute('id', 'favicon');
            favicon.setAttribute('rel', 'shortcut icon');
            document.head.appendChild(favicon);
        }
        return favicon;
    };

    var getEnvColor = function () {
        var matches = document.body.className.match(/environment-(local|alpha|beta|live)/i);
        var env = matches && matches[1] ? matches[1] : '';
        switch (env) {
            case 'alpha':
                return 'rgba(66, 109, 221, 0.99)';
            case 'beta':
                return '#ef32fa';
            case 'live':
                return '#ff0a00';
            default:
                return '#009805';
        }
    }

};

function throwError(message, type) {
    if (typeof(type) === 'undefined') type = 'error';
    var error = new Error(message);
    error.message = message;
    error.type = type;
    // pass error through window because onerror in FF have error argument
    window.onerrorError = error;
    throw error;
}

function template(name, vars, handler) {
    return server.action('template', {name: name, vars: vars}, handler);
}

function getInputData(containerId, skipIfNotDisplayed) {

    // skipIfNotDisplayed = true by default
    skipIfNotDisplayed = typeof skipIfNotDisplayed === 'undefined' ? true : skipIfNotDisplayed;

    var data = {};
    var inputs = getFormElements(containerId);

    for (var i = 0; i < inputs.length; i++) {
        var input = inputs[i];
        if (skipIfNotDisplayed && input.type !== 'hidden' && input.offsetHeight == 0 && input.offsetWidth == 0) continue;
        var name = input.getAttribute('name')
        if (name) {
            if (input.type == 'radio' && !input.checked) continue;
            if (input.type == 'checkbox') {
                data[name] = input.checked;
                continue;
            }
            data[name] = input.value;
        }
    }

    // expand names to arrays for names like "array[field]"
    var dataFinal = {};
    for (var i in data) {
        if (!data.hasOwnProperty(i)) continue;
        var path = i.replace(/]/g, '').split('[');
        var dest = dataFinal;
        for (var j = 0; j < path.length - 1; j++) {
            if (typeof dest[path[j]] !== 'object') dest[path[j]] = {};
            dest = dest[path[j]]
        }
        dest[path[j]] = data[i];
    }

    return dataFinal;

}

function getFormElements(containerId) {

    var container = $id(containerId);
    var elements = [];

    var toFind = ['input', 'select', 'button', 'textarea'];
    for (var i = 0; i < toFind.length; i++) {
        elements = elements.concat(
            Array.prototype.slice.call(
                container.getElementsByTagName(toFind[i])
            )
        );
    }

    return elements;

}

function evalScripts(html) {

    var container = document.createElement('div');
    container.innerHTML = html;

    var scripts = container.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
        var js = scripts[i].src ? server.getFile(scripts[i].src) : scripts[i].textContent;
        try {
            eval(js);
        } catch (e) {
            console.error('Error during evalScripts(html): ' + e);
        }
    }

}

function br() {
    return document.createElement('br');
}

function hide(el) {
    $(el) && ($(el).style.display = 'none');
}

function show(el) {
    $(el) && ($(el).style.display = '');
}

function showHide(elements, flag) {
    $$(elements).foreach(function (i, el) {
        if (!el) return;
        var action;
        if (typeof flag === 'undefined') {
            action = el.style.display === 'none' ? 'show' : 'hide';
        } else {
            action = flag ? 'show' : 'hide';
        }
        window[action](el);
    });
}

function findParent(el, nodeName) {
    if (!el.parentElement) return false;
    if (el.parentElement.nodeName === nodeName) {
        return el.parentElement;
    } else {
        return findParent(el.parentElement, nodeName);
    }
}

function createForm(params) {
    var form = document.createElement('form');
    form.setAttribute('method', 'POST');
    if (typeof params === 'object') {
        params.foreach(function (k, v) {
            var input = document.createElement('input');
            input.setAttribute('name', k);
            input.value = v;
            form.appendChild(input);
        });
    }
    return form;
}

Object.prototype.foreach = function (cb) {
    for (var i in this) {
        if (!this.hasOwnProperty(i)) continue;
        if (cb(i, this[i]) === false) break;
    }
};

function count(obj) {
    if (!obj) return 0;
    var type = obj.constructor.name;
    if (['Object', 'Array'].indexOf(type) === false) return 0;
    if (type === 'Array') return obj.length;
    var count = 0;
    obj.foreach(function () {
        count++
    });
    return count;
}

function createSelectElement(list) {

    var select = document.createElement('select');

    switch (list.constructor.name) {
        case 'Array':
            for (var i = 0; i < list.length; i++) {
                var opt = document.createElement('option');
                opt.innerHTML = list[i];
                select.options.add(opt);
            }
            break;
        case 'Object':
            list.foreach(function (k, v) {
                var opt = document.createElement('option');
                opt.value = k;
                opt.innerHTML = v;
                select.options.add(opt);
            });
            break;
        default :
            throw 'Exception: Invalid list';
    }

    return select;

}

function setPersistentVariable(name, value) {
    localStorage[name] = JSON.stringify(value);
}

function getPersistentVariable(name) {
    if (typeof localStorage[name] === 'undefined') {
        return null;
    }
    try {
        return JSON.parse(localStorage[name]);
    } catch (e) {
        console.log('Warning: getPersistentVariable: JSON.parse failed');
        return null;
    }
}

function stripSqlComments(sql) {
    return sql.replace(/^\s*(#|(-- )).*$/mg, '');
    // replace(/\/\*(.|[\n\r])*?\*\//g, '') - this rx replace star comments but isn't implemented because work incorrectly if string contain /* e.g. select 'asdf/*hh'; update value = '*/'
}

function html2text(html) {

    var replace = [
        [/&/g, '&amp;'],
        [/</g, '&lt;'],
        [/>/g, '&gt;']
    ];

    replace.foreach(function (k, v) {
        html = (html + '').replace(v[0], v[1]);
    });

    return html;

}

function ConsoleTextarea() {

    var me = this;
    var allTablesHtml = null;

    this.action = null;
    this.evalScripts = false;
    this.actionMethod = window.inst ? inst.action : server.action;
    this.wrapResultInPre = true;

    this.setMode = function (value) {
        this.editor.setOption("mode", value);
    };

    this.setValue = function (value) {
        if (value === null) value = '';
        this.editor.setValue(value);
        this.adjustHeight();
    };

    this.getValue = function (value) {
        return this.editor.getValue(value);
    };

    this.adjustHeight = function () {
        var linesCount = this.editor.lineCount();
        var height = linesCount > 7 ? 'auto' : 7 * this.editor.defaultTextHeight() + 10;
        this.editor.setSize('100%', height);
    };

    this.confirm = function () {
        return true;
    };

    this.beforeSubmit = function () {
    };

    this.submit = function () {
        if (!this.confirm()) return;
        this.beforeSubmit();
        var formData = getInputData('consoleForm');
        if (this.editor) {
            formData['consoleText'] = this.editor.getValue();
        }
        var me = this;
        this.actionMethod(this.action, formData, function (result) {
            if (me.wrapResultInPre) result = '<pre>' + result + '</pre>';
            $id('consoleResult').innerHTML = result;
            if (me.evalScripts) {
                evalScripts(result);
            }
        });
    };

    this.onChange = function () {
        me.adjustHeight();
    };

    var showTables = function () {
        var focus = function () {
            me.editor.focus();
        };
        new Popup({html: allTablesHtml}, focus, focus);
    };

    this.onShowTablesClick = function () {
        if (!allTablesHtml) {
            inst.action(
                'db/console/getAllTablesHtml',
                {},
                function (html) {
                    allTablesHtml = html;
                    showTables();
                }
            );
        } else {
            showTables();
        }
    };

    this.insertSelectSqlInConsole = function (table) {
        var pos = me.editor.getCursor();
        me.editor.setCursor({line: pos.line, ch: me.editor.getLine(pos.line).length});
        me.editor.getDoc().replaceRange(
            '\nSELECT * FROM `' + table + '` ORDER BY 1 DESC LIMIT 10;',
            me.editor.getCursor()
        );
        me.editor.setCursor({line: pos.line + 1, ch: 0});
    };

    this.editor = CodeMirror.fromTextArea(
        $id('consoleTextareaId'),
        {
            lineNumbers: true,
            keyMap: 'sublime',
            indentWithTabs: true,
            indentUnit: 4,
            smartIndent: true,
            matchBrackets: true,
            autofocus: true,
            extraKeys: {
                "Ctrl-Space": "autocomplete",
                "Ctrl-Enter": this.submit.bind(this)
            }
        }
    );
    this.editor.on('change', this.onChange);

}

function isStringEmpty(text) {
    if (getClassName(text) !== 'String') {
        return true;
    }
    var stringCopy = text + '';
    stringCopy = stringCopy.replace(/\s+/mg, '');
    return stringCopy === '';
}

consoleHint = {

    show: function () {
        $id('console-hint').className = 'show';
        if (consoleHint.hideTimeout) clearTimeout(consoleHint.hideTimeout);
        consoleHint.hideTimeout = setTimeout(consoleHint.hide, 4000);
    },

    hide: function () {
        $id('console-hint').className = '';
    }

}

renderDevtoolStatus = function () {
    var container = $id('devtool-status');
    container.innerHTML = "<img src='/app/skin/icon/loading.gif' />";

    server.action('devtool/getStatusHtml', null, function (html) {
        container.innerHTML = html;
        evalScripts(html);
    }, false);
}

function getTrueKeys(object) {
    var keys = [];
    object.foreach(function (k, v) {
        v && keys.push(k);
    });
    return keys;
}