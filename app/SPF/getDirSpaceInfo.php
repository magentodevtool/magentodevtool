<?php

namespace SPF;

function getDirSpaceInfo($dir = '.')
{
    return array(
        'free' => disk_free_space($dir),
        'total' => disk_total_space($dir),
    );
}
