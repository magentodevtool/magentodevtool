<?php

#incspf db
#incspf error

namespace SPF\deployment;

function getLocales()
{
    if (!\SPF\db()) {
        \SPF\error('Connection to db failed');
    }

    $query = 'SELECT DISTINCT value FROM `core_config_data` where path = \'general/locale/code\'';

    $locales = array('en_US' => 'en_US');
    foreach (\SPF\db()->query($query)->fetchAll() as $row) {
        $locales[$row['value']] = $row['value'];
    }

    return $locales;
}
