<?php

# incspf docker/getComposeProjectName

namespace SPF\docker;

function getContainerName($name)
{
    return \SPF\docker\getComposeProjectName() . '_' . $name . '_1';
}
