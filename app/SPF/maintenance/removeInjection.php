<?php

#incspf error

namespace SPF\maintenance;

function removeInjection(&$content)
{
    $content = preg_replace('~<\?php.*?' . preg_quote('/*devtool maintenance*/') . '.*?\?>?~sm', '', $content);
}
