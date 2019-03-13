<?php

foreach ($ARG as $branch) {
    if ($branch === 'master') {
        error('Why master');
    }
    if (!$inst->execOld('git push origin :%s', $branch)) {
        error('Failed on: ' . cmd('git push origin :%s', $branch));
    }
}