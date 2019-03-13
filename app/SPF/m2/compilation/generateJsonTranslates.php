<?php

# incspf m2/init

namespace SPF\m2\compilation;

// generate js-translation.json files in view_preprocessed folder
function generateJsonTranslates($generateParams)
{
    foreach ($generateParams as $params) {
        // for each translation new Magento initialization is needed
        $bootstrap = \SPF\m2\init($params['area']);
        $objectManager = $bootstrap->getObjectManager();
        $assetRepo = $objectManager->create('Magento\Framework\View\Asset\Repository');
        $params['module'] = '';
        $asset = $assetRepo->createAsset('js-translation.json', $params);
        $assetSource = $objectManager->create('\Magento\Framework\View\Asset\Source');
        // Info for debug: this method generates js-translation.json and returns its content
        $assetSource->getContent($asset);
    }
}
