<?php

# incspf exec
# incspf error
# incspf mage/local/fixDevRights
# incspf mage/remote/fixDevRights
# incspf mage/fixMageRights

namespace SPF\mage;

function fixRights($options)
{
    if ($options->mode === 'specific') {
        foreach ($options->fixes as $fix => $doFix) {
            if ($fix === 'dev' && $doFix === true) {
                if ($options->type === 'local') {
                    \SPF\mage\local\fixDevRights();
                } else {
                    \SPF\mage\remote\fixDevRights();
                }
            } elseif ($doFix === true) {
                \SPF\mage\fixMageRights($fix);
            }
        }
    } else {
        \SPF\mage\local\fixDevRights();
        \SPF\mage\fixMageRights('var');
        \SPF\mage\fixMageRights('media');
    }
}
