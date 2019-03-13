<?php

namespace SPF;

function error($message)
{
    throw new \Exception($message);
}