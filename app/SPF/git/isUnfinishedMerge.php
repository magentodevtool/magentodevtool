<?php

# incspf exec
# incspf git/getDir

namespace SPF\git;

function isUnfinishedMerge()
{
    return file_exists(getDir() . 'MERGE_HEAD');
}
