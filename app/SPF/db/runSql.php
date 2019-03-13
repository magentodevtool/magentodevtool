<?php
#incspf db/getCredentials

namespace SPF\db;

function runSql($sql)
{

    global $instInfo;

    if (!$dbCreds = getCredentials()) {
        return (object)array('error' => 'Can\'t get DB credentials');
    }

    $stdErrFile = '/tmp/mysql_error_' . uniqid();

    $command = sprintf(
        'MYSQL_PWD=%s mysql --default-character-set=utf8 -A -h%s -u%s -P%s %s -H -v -v -e %s 2>%s',
        escapeshellarg($dbCreds->password),
        escapeshellarg($dbCreds->host),
        escapeshellarg($dbCreds->username),
        escapeshellarg($dbCreds->port),
        escapeshellarg($dbCreds->dbname),
        escapeshellarg($sql),
        $stdErrFile
    );

    exec($command, $output);
    $output = utf8_encode(join("\n", $output));
    $error = file_get_contents($stdErrFile);
    @unlink($stdErrFile);

    if (strlen($output) > 1 * 1024 * 1024) {
        // ssh will not be able to transfer so big result
        $output = '';
        $error = 'Restriction: Output is more than 1MB, try to add/lessen limit';
    }

    return (object)compact('output', 'error');
}
