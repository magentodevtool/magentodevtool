<?php

$db = SQLite::getDb('devtool');

$queriesStr = stripSharpComments($ARG->consoleText);

// split sql queries into array by ";\n"
$queriesStr = $queriesStr . ";\n";
$queriesStr = preg_replace('~;[ \t]+\n~', ";\n", $queriesStr);
$queries = explode(";\n", $queriesStr);

$results = array();
foreach ($queries as $query) {
    $query = trim($query);
    $query = trim($query, ';');
    if ($query === '') {
        continue;
    }
    $result = array('query' => $query);

    try {
        $queryResult = @$db->query($query);
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        $results[] = $result;
        continue;
    }

    // changes() should be called before fetch to get correct value
    $result['affectedRows'] = $db->changes();

    // due to some bug fetch send insert second time so we shouldn't call fetch when insert (using numColumns which is 0 for insert)
    if (is_object($queryResult) && $queryResult->numColumns()) {
        // it was select
        $result['rows'] = array(); // - this line is important to be able to show "Empty set"
        while ($row = $queryResult->fetchArray(SQLITE3_ASSOC)) {
            $result['rows'][] = $row;
        }
    }

    $results[] = $result;
}

return template('sqlite/results', array('results' => $results));
