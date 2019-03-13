<?php

$name = $ARG->name;
$vars = @$ARG->vars;

if (strpos($name, '..') !== false) {
    error('Insecure template name');
}

return template($name, $vars, true);
