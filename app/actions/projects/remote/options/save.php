<?php

$optionsEncoded = $ARG->options;

$options = new stdClass();
foreach ($optionsEncoded as $projectNameEncoded => $projectOptions) {
    $options->{base64_decode($projectNameEncoded)} = $projectOptions;
}

\Projects\Remote::setOptions($options);
\Projects\Remote::setOptionsDefault('visibility', $ARG->defaultVisibility);
\Projects\Remote::setOptionsDefault('position', $ARG->defaultPosition);
