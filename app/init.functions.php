<?php

function includeDir($dir)
{
    $dir = rtrim($dir, '/') . '/';
    $files = glob($dir . '*');
    foreach ($files as $file) {
        if (is_dir($file)) {
            includeDir($file);
            continue;
        }
        require_once $file;
    }
}

function checkExtensions()
{

    $requiredExtensions = array(
        'pdo_mysql' => array('php5-mysql'),
        'curl' => array('php5-curl'),
        'ssh2' => array('libssh2-php'),
        'sqlite3' => array('sqlite3', 'php5-sqlite'),
    );

    $missingExtensions = array();
    foreach ($requiredExtensions as $requiredExtension => $packagesToInstall) {
        if (!extension_loaded($requiredExtension)) {
            $missingExtensions[$requiredExtension] = $packagesToInstall;
        }
    }

    if (!count($missingExtensions)) {
        return;
    }

    $output = '<pre><span style="color:red"><b>'
        . implode(', ', array_keys($missingExtensions))
        . '</b> required, just do:</span>';
    foreach ($missingExtensions as $missingExtension => $packagesToInstall) {
        foreach ($packagesToInstall as $packageToInstall) {
            $output .= "\n\tsudo apt-get install $packageToInstall";
            $output .= "\n\tsudo phpenmod $missingExtension";
        }
    }
    $output .= getWebserverRestartText();
    $output .= '</pre>';

    die($output);

}

function getWebserverRestartText()
{
    list ($name, $version) = explode('/', $_SERVER['SERVER_SOFTWARE'], 2);

    switch (strtolower($name)) {
        case 'apache':
            $string = "\n\tsudo service apache2-devtool reload";
            break;
        case 'nginx':
            $string = "\n\tsudo service " . getFpmServiceName() . " reload && sudo service nginx reload";
            break;
        default:
            $string = "\n\tRestart your Webserver";
    }


    return $string;
}
