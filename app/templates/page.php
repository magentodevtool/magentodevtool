<?php

switch ($view) {
    case 'projects':
        page()->title = 'PROJECTS';
        break;
    case 'installation':
        page()->title = @$_GET['project'] . ' / ' . @$_GET['name'];
        break;
    case 'magento/autologin/bo':
        page()->title = @$_GET['project'] . ' / ' . @$_GET['name'] . ': BO auto log in';
        break;
    case 'sqlite':
        page()->title = 'SQLite console';
        break;
    case '404':
        page()->title = '404';
        break;
    case 'projects/info':
        page()->title = 'Projects info';
        break;
    case 'installation/deployment/noSsh':
        page()->title = @$_GET['project'] . ' / ' . @$_GET['name'] . ': NO SSH deployment instruction';
        break;
    case 'timeEstimation':
        page()->title = 'Time Estimation';
        break;
    case 'timeEstimation/details':
    case 'timeEstimation/details2':
        page()->title = 'TE details';
        break;
    default:
        page()->title = '???';
        break;
}

$environment = preg_match('~(local|alpha|beta|live)~i', @$_GET['name'], $ms) ? strtolower($ms[1]) : 'local';
page()->bodyClass = "environment-$environment";
