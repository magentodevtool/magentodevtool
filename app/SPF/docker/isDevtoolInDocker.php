<?php

namespace SPF\docker;

function isDevtoolInDocker()
{
    return !empty($_ENV['DEVTOOL_DOCKER']);
}
