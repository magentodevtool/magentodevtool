<?php

require_once __DIR__ . '/init.php';

if (!db(getDbCredentials())) {
    error('Connection to db failed');
}

// if runner
define('ISM_BASE_RUNNER_INCLUDED', true);

require_once MAGE_ROOT . 'app/Mage.php';

// disable cache to get only fresh links
Mage::app('admin', 'store', array('global_ban_use_cache' => true));

$links = array();
$urlModel = Mage::getSingleton('core/url');
preg_match('~/([^/]+)$~', __FILE__, $ms);
$thisFile = $ms[1];
foreach (db()->query('select * from core_store where is_active=1') as $store) {
    if ($store['code'] !== 'admin') {
        $url = $urlModel->getUrl('', array('_store' => $store['store_id'], '_nosid' => true));
        $url = str_replace("/$thisFile/", '/', $url);
        $url = preg_replace("~/zdevtool/RAI-[0-9]+/~", "/", $url);
        $links[$url][] = $store['code'];
    } else {
        $beUrl = Mage::getSingleton('adminhtml/url')->getUrl('adminhtml');
        $beUrl = str_replace("/$thisFile/", '/', $beUrl);
        $beUrl = preg_replace(
            array(
                '~/zdevtool/RAI-[0-9]+/~',
                '~\?SID=.+$~',
                '~/key/[^/]+/$~',
                '~/index/index/$~',
            ),
            array('/', '', '/', '/'),
            $beUrl
        );
        $links[$beUrl][] = 'admin';
    }
}

die(json_encode($links));