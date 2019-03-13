<?php

namespace SPF\composer;

function isBuilt()
{
    return file_exists('vendor/autoload.php');
}
