<?php

$tags = is_array($ARG) ? $ARG : array($ARG);

foreach ($tags as $tag) {

    if (!$tag->local) {
        $inst->execOld('git push origin :%s', 'refs/tags/' . $tag->name);
    }

    $inst->execOld('git tag -d %s', $tag->name);

}