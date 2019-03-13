<?php

$branchName = $ARG;
$branchName = preg_replace('~[^[:alnum:].]+~', '-', $branchName);

// support dot between digits e.g. Feature-3.0
$branchName = preg_replace('~\.+~', '.', $branchName);
for ($i = 0; $i < 2; $i++) {
    $branchName = preg_replace('~([^0-9])\.+([^0-9])~', '$1$2', $branchName);
}

$branchName = trim($branchName, '-.');
return $branchName;