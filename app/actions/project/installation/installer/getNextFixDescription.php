<?php

if ($nextFix = $inst->getNextFix()) {
    return $inst->getFixDescription($nextFix);
}

return false;