<?php

namespace SPF\depins;

function getName($file, $type = 'db')
{

    $fileEnd = $type === 'db' ? '.txt' : ".$type.txt";

    if (!preg_match('~app/depins/(.+' . preg_quote($fileEnd) . ')$~', $file, $matches)) {
        return false;
    }

    if ($type === 'db') {
        if (preg_match('~\.pre\.txt$~', $file)) {
            return false;
        }
    }

    if (strpos($file, '/archive/') !== false) {
        return false;
    }

    return $matches[1];

}
