<?php

// temporary fix: no memory limit otherwise generation will fail when lot of stores (Magento config cache isn't freed)
ini_set('memory_limit', -1);

if (!$deployment->mage2->doCompileStaticContent) {
    return 1;
}
if ($deployment->mage2->staticContentCompilationType === 'standard') {
    return 1;
}

$locales = (array)$deployment->mage2->locales;
$error = false;
try {
    $staticContentDir = $localInst->_docRoot . 'static/';
    $localesFolder = glob($staticContentDir . '{adminhtml,frontend}/*/*/*', GLOB_BRACE);
    array_walk($localesFolder, function (&$value) use ($staticContentDir) {
        $value = str_replace($staticContentDir, '', $value);
    });

    $generateParams = [];
    foreach ($localesFolder as $localeFolder) {
        preg_match('~^(adminhtml|frontend)/(.+?/.+?)/(.*)$~', $localeFolder, $matches);
        $generateParams[] = [
            'area' => $matches[1],
            'theme' => $matches[2],
            'locale' => $matches[3],
        ];
    }

    // generate js-translation.json files in view_preprocessed folder
    $localInst->spf('m2/compilation/generateJsonTranslates', $generateParams);

    $sourceDir = $localInst->_appRoot . 'var/view_preprocessed/source/';
    $jsonTranslateFiles = glob($sourceDir . '{adminhtml,frontend}/*/*/*/js-translation.json', GLOB_BRACE);
    array_walk($jsonTranslateFiles, function (&$value) use ($sourceDir) {
        $value = str_replace($sourceDir, '', $value);
    });

    // copy js-translation.json files from view_preprocessed to pub/static
    foreach ($jsonTranslateFiles as $src) {
        $localInst->exec("cp -r %s %s", $sourceDir . $src, $staticContentDir . $src);
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

$generatedForLocales = implode(', ', $locales);
deploymentDialog(
    'm2/compilation/generateJsonTranslates/result',
    compact('error', 'generatedForLocales')
);
