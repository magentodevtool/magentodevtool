<?php

# incspf db/getCredentials

namespace SPF;

function db()
{
    static $pdo;
    if (!is_null($pdo)) {
        return $pdo;
    }
    $cred = db\getCredentials();
    try {
        $pdo = new \PDO('mysql:host=' . $cred->host . ';port=' . $cred->port, $cred->username, $cred->password);
        $pdo->query('use `' . $cred->dbname . '`');
    } catch (\Exception $e) {
        return false;
    }
    return $pdo;
}
