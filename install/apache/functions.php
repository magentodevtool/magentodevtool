<?php

function cleanPreviousSetupV1()
{

    killDevtoolInstance();
    usleep(250000); // wait until port free

    // delete old files
    foreach (array('apache2.devtool.conf', 'ports.devtool.conf', 'sites-enabled-devtool') as $file) {
        exec("rm -rf /etc/apache2/$file");
    }

    // rm from rc.local
    $rcLocal = file_get_contents('/etc/rc.local');
    if (preg_match('~apache2\.devtool\.conf~', $rcLocal)) {
        $rcLocal = preg_replace('~apache.+\s+-f\s+/etc/apache2/apache2\.devtool\.conf\n~im', '', $rcLocal);
        file_put_contents('/etc/rc.local', $rcLocal);
    }

}

function registerHost()
{
    $hosts = file('/etc/hosts');
    foreach ($hosts as $i => &$line) {
        $line = trim($line);
        if (preg_match('~^\s*[0-9.]+\s+([^\s]+\s+)*devtool\.local(\s+[^\s]+)*\s*$~ism', $line, $matches)) {
            if (count($matches) > 1) {
                $line = str_replace('devtool.local', '', $line);
            } else {
                unset($hosts[$i]);
            }
        }
    }
    $hosts[] = "127.0.0.1 devtool.local";
    file_put_contents('/etc/hosts', implode("\n", $hosts));
}

function configureDevToolApacheInstance()
{
    // copy apache config
    if (!file_exists('/etc/apache2/apache2.conf')) {
        die("Error: file /etc/apache2/apache2.conf not found\n");
    }
    copy('/etc/apache2/apache2.conf', '/etc/apache2/apache2.devtool.conf');

    // detect user for dev tool
    $fileInfoCmd = 'ls -l ' . __FILE__;
    $fileInfo = trim(`$fileInfoCmd`);
    preg_match('~[^ ]+ [^ ]+ ([^ ]+) ([^ ]+) ~', $fileInfo, $userInfo);
    if (count($userInfo) !== 3) {
        die("Error: can't detect user for devtool\n");
    }
    $devUser = $userInfo[1];
    $devGroup = $userInfo[2];

    // configure devtool apache
    $apache2DevToolConf = file_get_contents('/etc/apache2/apache2.devtool.conf');
    $apache2DevToolConf = preg_replace(
        array(
            '~\nUser [^\n]+\n~',
            '~\nGroup [^\n]+\n~',
            '~\nErrorLog [^\n]+\n~',
            '~Include ports.conf~',
            '~Include sites-enabled~', // apache 2.2
            '~IncludeOptional sites-enabled/\*\.conf~', // apache 2.4
            '~\nPidFile [^\n]+\n~',
        ),
        array(
            "\nUser $devUser\n",
            "\nGroup $devGroup\n",
            "\nErrorLog /var/log/apache2/error.devtool.log\n",
            "Include ports.devtool.conf",
            "Include sites-enabled-devtool",
            "IncludeOptional sites-enabled-devtool/*.conf",
            "\nPidFile \"/var/run/apache2/apache2devtool\$SUFFIX.pid\"\n",

        ), $apache2DevToolConf);
    file_put_contents('/etc/apache2/apache2.devtool.conf', $apache2DevToolConf);
    file_put_contents('/etc/apache2/ports.devtool.conf', "Listen 81");

    // replace ${APACHE_LOG_DIR} in conf.d/other-vhosts-access-log
    $otherHostsConfFile = '/etc/apache2/conf.d/other-vhosts-access-log';
    if (is_file($otherHostsConfFile)) {
        $otherHostsConf = file_get_contents($otherHostsConfFile);
        $otherHostsConf = str_replace('${APACHE_LOG_DIR}', '/var/log/apache2', $otherHostsConf);
        file_put_contents($otherHostsConfFile, $otherHostsConf);
    }
}

function createDevToolVitrualHost()
{
    if (!is_dir('/etc/apache2/sites-enabled-devtool')) {
        mkdir('/etc/apache2/sites-enabled-devtool');
    }
    $documentRoot = realpath(__DIR__ . '/../..');
    $directoryAccess = "Order allow,deny\n                Allow from all";
    if (version_compare('2.4.0', getApacheVersion()) === -1) {
        $directoryAccess = "Require all granted";
    }
    $virtualHost = <<<asdf
<VirtualHost *:81>
        ServerName devtool.local
        DocumentRoot $documentRoot
        CustomLog /var/log/apache2/access.devtool.log combined
        ErrorLog /var/log/apache2/error.devtool.log
        <Directory $documentRoot>
                Options +Indexes +FollowSymLinks -MultiViews
                AllowOverride All
                $directoryAccess
        </Directory>
</VirtualHost>
asdf;
    file_put_contents('/etc/apache2/sites-enabled-devtool/default.conf', $virtualHost);
}

function registerAutorun()
{
    $rcLocal = file_get_contents('/etc/rc.local');
    if (!strpos($rcLocal, 'devtool')) {
        $rcLocal = preg_replace('~\nexit 0~', "\napachectl -f /etc/apache2/apache2.devtool.conf\nexit 0", $rcLocal);
        file_put_contents('/etc/rc.local', $rcLocal);
    }
}

function runApacheDevToolInstance()
{
    `apachectl -f /etc/apache2/apache2.devtool.conf`;
}

function getApacheVersion()
{
    preg_match('~Apache/([0-9]+\.[0-9]+\.[0-9]+)~ism', `apache2 -v`, $ms);
    return $ms[1];
}

function cleanPreviousSetupV2()
{

    $SUFFIX = APACHE_INSTANCE_SUFFIX;

    killDevtoolInstance();
    usleep(250000); // wait until port free

    $filesToRemove = array(
        "/etc/init.d/apache2-$SUFFIX",
        "/etc/apache2-$SUFFIX",
        "/usr/local/sbin/a2enmod-$SUFFIX",
        "/usr/local/sbin/a2dismod-$SUFFIX",
        "/usr/local/sbin/a2ensite-$SUFFIX",
        "/usr/local/sbin/a2dissite-$SUFFIX",
        "/usr/local/sbin/apache2ctl-$SUFFIX",
        "/etc/logrotate.d/apache2-$SUFFIX",
        "/var/log/apache2-$SUFFIX",
    );

    $filesToRemove = array_merge($filesToRemove, glob("/etc/rc*.d/*apache2-$SUFFIX"));

    foreach ($filesToRemove as $fileToRemove) {
        exec('rm -rf ' . $fileToRemove);
    }

}

function createSeparateApacheInstance()
{
    $SUFFIX = APACHE_INSTANCE_SUFFIX;
    exec('sh /usr/share/doc/apache2/examples/setup-instance ' . $SUFFIX, $o, $r);
    if ($r !== 0) {
        die('Failed to create apache instance');
    }
    // clean sites
    exec("cd /etc/apache2-$SUFFIX && rm -rf sites-available/* sites-enabled/*");
}

function configureApacheInstance()
{

    $SUFFIX = APACHE_INSTANCE_SUFFIX;
    $apache2ConfFile = "/etc/apache2-$SUFFIX/apache2.conf";
    $apache2PortsFile = "/etc/apache2-$SUFFIX/ports.conf";

    // detect user for dev tool
    $fileInfoCmd = 'ls -l ' . __FILE__;
    $fileInfo = trim(`$fileInfoCmd`);
    preg_match('~[^ ]+ [^ ]+ ([^ ]+) ([^ ]+) ~', $fileInfo, $userInfo);
    if (count($userInfo) !== 3) {
        die("Error: can't detect user for devtool\n");
    }
    $devUser = $userInfo[1];
    $devGroup = $userInfo[2];
    if ($devUser === 'root') {
        die("Error: root is owner of devtool sources, you must be owner, please fix \n");
    }

    // configure devtool apache
    $apache2Conf = file_get_contents($apache2ConfFile);
    $apache2Conf = preg_replace(
        array(
            '~\nUser [^\n]+\n~',
            '~\nGroup [^\n]+\n~',
        ),
        array(
            "\nUser $devUser\n",
            "\nGroup $devGroup\n",
        ), $apache2Conf);
    file_put_contents($apache2ConfFile, $apache2Conf);

    // set 81 port
    $apache2Ports = file_get_contents($apache2PortsFile);
    $apache2Ports = preg_replace(
        array(
            '~\nNameVirtualHost\s+([^:]+):80~ism',
            '~\nListen\s+([^:\s]+:)?80~ism',
            '~\n([ \t]+)Listen~ism', // comment Listen inside "if module" sections
        ),
        array(
            "\nNameVirtualHost \$1:81",
            "\nListen \${1}81",
            "\n\$1# Listen",
        ),
        $apache2Ports
    );
    file_put_contents($apache2PortsFile, $apache2Ports);

    // add virtual host
    $documentRoot = realpath(__DIR__ . '/../..');
    $directoryAccess = "Order allow,deny\n                Allow from all";
    if (version_compare('2.4.0', getApacheVersion()) === -1) {
        $directoryAccess = "Require all granted";
    }

    $nameVirtualHost = '*:81';
    foreach (array('ports.conf', 'apache2.conf') as $file) {
        $file = "/etc/apache2-$SUFFIX/" . $file;
        if (!file_exists($file)) {
            continue;
        }
        if (preg_match('~\s*NameVirtualHost\s+([^\s]+)~ism', file_get_contents($file), $ms)) {
            $nameVirtualHost = $ms[1];
        }
    }

    $virtualHost = <<<asdf
<VirtualHost $nameVirtualHost>
        ServerName devtool.local
        DocumentRoot $documentRoot
        CustomLog /var/log/apache2-$SUFFIX/access.log combined
        ErrorLog /var/log/apache2-$SUFFIX/error.log
        <Directory $documentRoot>
                Options +Indexes +FollowSymLinks -MultiViews
                AllowOverride All
                $directoryAccess
        </Directory>
</VirtualHost>
asdf;
    file_put_contents("/etc/apache2-$SUFFIX/sites-available/devtool.conf", $virtualHost);
    exec("cd /etc/apache2-$SUFFIX/sites-enabled && ln -s ../sites-available/devtool.conf");

}

function killDevtoolInstance()
{
    exec("ps aux |grep -e 'apache2.*devtool'", $o, $r);
    foreach ($o as $line) {
        if (preg_match('~^root\s+([0-9]+)\s.+apache2.*devtool~', $line, $matches) && !strpos($line, 'grep')) {
            $masterPid = $matches[1];
            exec("kill $masterPid");
            break;
        }
    }
}

function registerAutorunV2()
{
    // create the same symlinks as apache2 have
    $SUFFIX = APACHE_INSTANCE_SUFFIX;
    foreach (glob('/etc/rc*.d/*apache2') as $file) {
        $pathInfo = pathinfo($file);
        $symlinkName = $pathInfo['basename'] . "-$SUFFIX";
        exec("cd {$pathInfo['dirname']} && ln -s ../init.d/apache2-$SUFFIX $symlinkName");
    }
}

function runDevtool()
{
    `service apache2-devtool start`;
}
