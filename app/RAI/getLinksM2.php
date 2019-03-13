<?php
require_once __DIR__ . '/init.php';

$bootstrap = initMagento2();
$objectManager = $bootstrap->getObjectManager();

die(json_encode(getLinks()));

function getLinks()
{
    global $objectManager;
    $db = $objectManager->get('Magento\Framework\App\ResourceConnection')->getConnection();

    $links = array();
    $query = 'select * from store';
    $stores = $db->fetchAll($query);

    $storeModel = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
    $beModel = $objectManager->get('Magento\Backend\Model\Url');
    foreach ($stores as $store) {
        if ($store['code'] !== 'admin') {
            $url = $storeModel->getStore($store['store_id'])->getBaseUrl();
        } else {
            $url = $beModel->getUrl('adminhtml', array('_nosid' => true));
        }
        $scriptName = basename(__FILE__);
        $url = str_replace($scriptName, 'index.php', $url);
        $seoRewrites = strpos($url, 'index.php') === false;
        if ($store['code'] === 'admin') {
            $parsed = parse_url($url);
            $pathParts = explode('/', $parsed['path']);
            $beFrontName = $seoRewrites ? $pathParts[1] : $pathParts[1] . '/' . $pathParts[2];
            $url = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '') . '/' . $beFrontName . '/';
        }
        $links[$url][] = $store['code'];
    }
    return $links;
}
