window.onerror = function (message, url, lineNumber, column, error) {
    error = error ? error : window.onerrorError;
    var type = error.type ? error.type : 'error';
    if (type !== 'info') consoleHint.show();
    if (type !== 'error') {
        console[type](error.message);
        return true;
    }
};

console.originWarn = console.warn;
console.warn = function (message) {
    console.originWarn(message);
    consoleHint.show();
};

console.originError = console.error;
console.error = function (message) {
    console.originError(message);
    consoleHint.show();
};
