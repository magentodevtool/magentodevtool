<?php

namespace SPF\generation\scss;

function getThemes()
{
    $themes = array_merge(
        glob('skin/frontend/*/*/scss/config.rb', GLOB_BRACE)
    );

    array_walk($themes, function (&$value) {
        $value = str_replace('scss/config.rb', '', $value);
    });

    foreach ($themes as $key => $value) {
        $files = glob($value . 'scss/*.scss');
        if (empty($files)) {
            unset($themes[$key]);
        }
    }

    return $themes;
}
