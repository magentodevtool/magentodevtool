function Installer() {

    this.start = function () {
        $id('result').innerHTML = '<br>Installation started..';
        this.install();
    }

    this.install = function () {
        var me = this;
        inst.action('installer/getNextFixDescription', me,
            function (nextFix) {
                if (nextFix) {
                    me.addMessage('<br>&nbsp;' + nextFix + '.. ');
                    inst.action('installer/install', me,
                        function (result) {
                            if (result.message) {
                                me.addMessage(result.message);
                            }
                            if (result.continue) {
                                me.addMessage('done');
                                me.install();
                            }
                        }
                    );
                } else {
                    var message = '<br>Done.';
                    if (inst.project.type !== 'simple') {
                        message += '<br><span style="color: #d74a00">Please flush caches to apply changes in database.</span>';
                    }
                    message += '<br><button onclick="location.reload()">Continue</button>';
                    me.addMessage(message);
                }
            }
        );
    }

    this.addMessage = function (message) {
        $id('result').innerHTML += message;
        evalScripts(message);
    }

}
