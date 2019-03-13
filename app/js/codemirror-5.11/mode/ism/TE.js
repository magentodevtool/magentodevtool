// reused shell/shell.js

(function (mod) {
    if (typeof exports == "object" && typeof module == "object") // CommonJS
        mod(require("../../lib/codemirror"));
    else if (typeof define == "function" && define.amd) // AMD
        define(["../../lib/codemirror"], mod);
    else // Plain browser env
        mod(CodeMirror);
})(function (CodeMirror) {
    "use strict";

    CodeMirror.defineMode('te', function () {

        var words = {};

        function define(style, string) {
            var split = string.split('; ');
            for (var i = 0; i < split.length; i++) {
                words[split[i]] = style;
            }
        };

        // Atoms
        define('atom', '');

        // Keywords
        define('keyword',
            'transferring' +
            '; platform learning' +
            '; implementation' +
            '; code review' +
            '; code review reworks' +
            '; transferring to qa' +
            '; assistance to qa' +
            '; internal reworks' +
            '; pass to beta' +
            '; external reworks' +
            '; deployments' +
            '; overhead' +
            '; missed points' +
            '; already spent'
        );

        // Commands
        define('builtin', '');

        function tokenBase(stream, state) {

            var sol = stream.sol();
            var ch = stream.next();
            var prevCh = stream.string.charAt(stream.pos - 2);
            // start of phrase
            var sop = stream.string.search(/\S/) === stream.pos - 1;

            if (ch === '#') {
                stream.skipToEnd();
                return 'comment';
            }
            if (sop) {
                if (/\s+(0[mhd]|\+0%)(\s|$)/.test(stream.string)) {
                    stream.skipToEnd();
                    return 'zeroline';
                }
            }
            if (sol) {
                stream.eatWhile(/[\w]/);
                var phrase1 = stream.current();
                stream.eatWhile(/ /);
                stream.eatWhile(/[\w]/);
                var phrase2 = stream.current();
                stream.eatWhile(/ /);
                stream.eatWhile(/[\w]/);
                var phrase3 = stream.current();
                var phrase3Key = phrase3.replace(/ +/g, ' ').toLowerCase();
                if (words.hasOwnProperty(phrase3Key)) {
                    return words[phrase3Key];
                }
                var phrase2Key = phrase2.replace(/ +/g, ' ').toLowerCase();
                if (words.hasOwnProperty(phrase2Key)) {
                    stream.backUp(phrase3.length - phrase2.length);
                    return words[phrase2Key];
                }
                var phrase1Key = phrase1.replace(/ +/g, ' ').toLowerCase();
                if (words.hasOwnProperty(phrase1Key)) {
                    stream.backUp(phrase3.length - phrase1.length);
                    return words[phrase1Key];
                }
                stream.backUp(phrase3.length - 1);
            }

            // highlight time ranges
            if (/\s/.test(prevCh)) {
                stream.eatWhile(/[+0-9\.\-mhd%]/);
                var expr = stream.current();
                var floatRx = '[0-9]+(\\.[0-9]+)?';
                var rangeRx = '((' + floatRx + ')(m|h|d))|((' + floatRx + ')-(' + floatRx + ')(m|h|d))';
                var percRx = '\\+([0-9]+)%';
                var timeExprRx = new RegExp('^(' + rangeRx + ')$|^(' + percRx + ')$');
                var nextCh = stream.string.charAt(stream.pos);
                if (timeExprRx.test(expr) && (/\s/.test(nextCh) || stream.eol())) {
                    return 'atom';
                }
                stream.backUp(expr.length - 1);
            }

        }

        function tokenize(stream, state) {
            return (state.tokens[0] || tokenBase)(stream, state);
        }

        return {
            startState: function () {
                return {tokens: []};
            },
            token: function (stream, state) {
                return tokenize(stream, state);
            },
            lineComment: '#'
        };
    });

    CodeMirror.defineMIME('ism/te', 'te');

});
