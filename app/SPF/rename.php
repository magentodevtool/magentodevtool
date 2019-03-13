<?php

#incspf exec

namespace SPF;

function rename($src, $dst)
{
    // php rename doesn't work on Cloud for folders, see commit comment
    exec('mv %s %s', $src, $dst);
}
