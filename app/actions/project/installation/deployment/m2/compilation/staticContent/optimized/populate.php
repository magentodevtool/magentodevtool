<?php

if (!$deployment->mage2->doCompileStaticContent) {
    return 1;
}
if ($deployment->mage2->staticContentCompilationType === 'standard') {
    return 1;
}

$locales = (array)$deployment->mage2->locales;
if (count($locales) === 1) {
    // nothing to populate
    return 1;
}

$localesToPopulate = $locales;
$srcLocale = array_shift($localesToPopulate);
$error = false;
try {
    // remove locale specific files which are generate by Magento2 on the fly
    $localInst->exec([
        'rm -rf pub/static/*/*/*/*/css/email-inline.min.css',
        'rm -rf pub/static/*/*/*/*/css/email.min.css',
    ]);

    $srcDirs = array_merge(
        glob($localInst->_docRoot . 'static/_requirejs/{adminhtml,frontend}/*/*/', GLOB_BRACE),
        glob($localInst->_docRoot . 'static/{adminhtml,frontend}/*/*/', GLOB_BRACE),
        glob($localInst->_appRoot . 'var/view_preprocessed/{css,source}/*/*/*/', GLOB_BRACE)
    );

    foreach ($srcDirs as $srcDir) {
        $src = $srcDir . $srcLocale . '/';
        foreach ($localesToPopulate as $key => $locale) {
            $dest = $srcDir . $locale . '/';
            $localInst->exec("cp -r %s %s", $src, $dest);
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$populatedLocales = implode(', ', $localesToPopulate);
deploymentDialog(
    'm2/compilation/populate/result',
    compact('error', 'populatedLocales')
);
