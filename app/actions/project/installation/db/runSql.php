<?php

// for preg_replace_callback if big output
ini_set('pcre.backtrack_limit', 10000000);

$cutReadUncommitted = function (&$output) {
    $setProfilingResultRx = '~--------------\s+set transaction isolation level read uncommitted\s+--------------\s+Query OK, 0 rows affected\s+~is';
    $output = preg_replace($setProfilingResultRx, '', $output);
};

$fetchProfile = function (&$output, $readUncommitted) {
    $setProfilingResultRx = '~^--------------\s+set profiling=1\s+--------------\s+[^\n]*\s+~is';
    $output = preg_replace($setProfilingResultRx, '', $output);
    $showProfilesResultRx = '~--------------\s+show profiles\s+--------------\s+<table border=1>((?:(?!</table>).)+)</table>[^\n]*\s+Bye$~ism';
    $profile = array('total' => (object)array('query' => 0, 'duration' => 0), 'details' => array());
    if (preg_match($showProfilesResultRx, $output, $ms)) {
        $output = preg_replace($showProfilesResultRx, 'Bye', $output);
        if (preg_match_all(
            '~<TR><TD>([0-9]+)</TD><TD>([0-9.]+)</TD><TD>((?:(?!</TD>).)+)</TD></TR>~ism',
            $ms[1], $ms2)
        ) {
            foreach ($ms2[0] as $k => $v) {
                if ($readUncommitted && ($k === 0)) {
                    continue;
                }
                $profile['details'][] = (object)array(
                    'query' => $ms2[3][$k],
                    'duration' => $ms2[2][$k],
                );
                $profile['total']->query++;
                $profile['total']->duration += $ms2[2][$k];
            }
        }
    } else {
        $output = preg_replace('~--------------\s+show profiles\s+--------------\s+Empty set\s+Bye$~ism', 'Bye',
            $output);
    }
    return (object)$profile;
};

$escapeMysqlOutput = function (&$output) {

    $cutTables = function ($matches) {
        global $tables;
        $tableId = uniqid();
        $tables[$tableId] = $matches[0];
        return " TABLE_$tableId ";
    };

    $restoreTables = function ($matches) {
        global $tables;
        return $tables[$matches[1]];
    };

    // Escape SQL queries but not tables (tables already escaped by MySQL by -H argument)
    global $tables;
    $tables = array();
    $output = preg_replace_callback('~(--------------\s+<TABLE BORDER=1>.*?</TABLE>[^\n]+\n+((--------------)|(Bye)))~ism',
        $cutTables, $output);
    $output = htmlspecialchars($output);
    $output = preg_replace_callback('~ TABLE_([0-9a-f]+) ~', $restoreTables, $output);

};

$inst->vars->set('lastDbQueries', (string)$ARG->consoleText);

$sql = $ARG->consoleText;

if ($ARG->readUncommitted) {
    $sql = 'set transaction isolation level read uncommitted;;' . $sql;
}

// ; - to don't add \n right after commands like "use database"; \n - to close sharp comment at the end; /**/ - to close /* comment; ;; - to delimit any ; or ;;
$sql = 'set profiling=1;;' . $sql . ";\n/**/;;show profiles";

$result = $inst->spf('db/runSql', $sql);
$output = $result->output;
$error = $result->error;

if ($ARG->readUncommitted) {
    $cutReadUncommitted($output);
}

$profile = $fetchProfile($output, $ARG->readUncommitted);

$escapeMysqlOutput($output);

$output = preg_replace('~Bye$~', '', $output);

list($microTime, $time) = explode(' ', microtime());
$time = ($time - START_TIME) + ($microTime - START_MICROTIME);

return $inst->form('db/console/result', compact('output', 'error', 'profile', 'time'));
