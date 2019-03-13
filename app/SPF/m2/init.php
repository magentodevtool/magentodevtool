<?php

namespace SPF\m2;

function init($areaCode = 'adminhtml')
{
    if (isset($_SERVER['MAGE_MODE']) && !in_array($_SERVER['MAGE_MODE'], array('production', 'developer'))) {
        // fix for: Uncaught InvalidArgumentException: Unknown application mode
        unset($_SERVER['MAGE_MODE']);
    }

    require_once 'app/bootstrap.php';
    $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
    $objectManager = $bootstrap->getObjectManager();
    $objectManagerConfigLoader = $objectManager->get('Magento\Framework\ObjectManager\ConfigLoaderInterface');
    $objectManager->configure($objectManagerConfigLoader->load($areaCode));
    $objectManager->get('Magento\Framework\App\State')->setAreaCode($areaCode);
    return $bootstrap;
}
