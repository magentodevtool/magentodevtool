<?php

function getToDoList($localInst)
{
    global $deployment;
    $toDo = [];

    if ($deployment->mage2->doCompileDi) {
        $toDo [] = 'php bin/magento setup:di:compile -vvv';
    }

    if (!$deployment->mage2->doCompileStaticContent) {
        return $toDo;
    }

    if ($deployment->mageVersion >= '2.2') {
        $toDoCmd = 'php bin/magento setup:static-content:deploy %s -f -s compact -vvv';
    } else {
        $toDoCmd = 'php bin/magento setup:static-content:deploy %s -vvv';
    }
    $locales = $deployment->mage2->locales;
    foreach ($locales as $locale) {
        $toDo[] = shellescapef($toDoCmd, $locale);
        if ($deployment->mageVersion < '2.2' && $deployment->mage2->staticContentCompilationType === 'optimized') {
            break;
        }
    }

    return $toDo;
}
