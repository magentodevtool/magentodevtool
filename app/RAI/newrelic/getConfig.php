<?php

die(json_encode(array(
    'enabled' => (bool)ini_get('newrelic.enabled'),
    'appname' => ini_get('newrelic.appname'),
)));

exit(1);
