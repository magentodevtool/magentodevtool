<?php

# incspf mage/getM2EnvConfig

namespace SPF\mage;

function getM2Mode()
{
    $m2EnvConfig = getM2EnvConfig();
    if (!$m2EnvConfig || !isset($m2EnvConfig['MAGE_MODE'])) {
        return false;
    }
    return $m2EnvConfig['MAGE_MODE'];
}