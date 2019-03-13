<?php

namespace Project\Installation;

class Dump
{
    /**
     * @var \Project\Installation $inst
     */
    protected $inst;
    protected $tablesInfo = array();

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    public function create($fileName, $singleTransaction, $tables = 'all', $noData = false)
    {
        $singleTransaction = $singleTransaction ? 'enableST' : 'disableST';
        $noData = $noData ? 'withoutData' : 'withData';
        if (!$result = $this->inst->execRaiScript(
            'db/backup.php',
            array(
                '%s %s %s %s',
                $fileName,
                $singleTransaction,
                json_encode($tables),
                $noData
            ))
        ) {
            error($this->inst->execOutput);
        }
        return $result;
    }

    public function getInfo($dump)
    {
        if (isset($this->tablesInfo[$dump])) {
            return $this->tablesInfo[$dump];
        }

        $dumpFile = $this->inst->_appRoot . 'var/' . $dump;
        $tablesInfo = array();
        $queriesCount = 0;
        $queryDelimiter = ';';
        $fh = fopen($dumpFile, 'r');

        while (!feof($fh)) {
            $line = fgets($fh);

            if (preg_match('~^DELIMITER (.{1,2})$~', $line, $ms)) {
                $queryDelimiter = $ms[1];
                $queriesCount++;
            } elseif (preg_match('~' . preg_quote($queryDelimiter) . '$~', $line)) {
                $queriesCount++;
            }

            //search tables and columns
            if (preg_match('~^CREATE TABLE `([^`]+)~', $line, $matches)) {
                $tableName = $matches[1];
                $tablesInfo[$tableName] = array(
                    'columnsCount' => 0,
                    'textColumnsCount' => 0,
                    'dataSize' => 0,
                    'insertQueriesCount' => 0
                );
                while (strpos($line, ';') === false) {
                    $line = fgets($fh);
                    if (preg_match('~^[\s]*`~', $line)) {
                        $tablesInfo[$tableName]['columnsCount']++;
                        if (preg_match('~varchar|text|date~i', $line)) {
                            $tablesInfo[$tableName]['textColumnsCount']++;
                        }
                    }
                }
                $queriesCount++;
            }

            //search data for table
            if (preg_match('~^INSERT +(IGNORE )?INTO~', $line) && !empty($tableName)) {
                $tablesInfo[$tableName]['dataSize'] += $this->getInsertQuerySize($line, $tablesInfo, $tableName);
                $tablesInfo[$tableName]['insertQueriesCount']++;
            }
        }
        fclose($fh);

        uasort($tablesInfo, function ($a, $b) {
            if ($a['dataSize'] == $b['dataSize']) {
                return 0;
            }
            return ($a['dataSize'] < $b['dataSize']) ? 1 : -1;
        });

        $this->tablesInfo[$dump] = $tablesInfo;
        return array(
            'queriesCount' => $queriesCount,
            'tables' => $tablesInfo,
        );
    }

    public function getInsertQuerySize($insertQuery, $tablesInfo, $tableName)
    {

        // trim \n and ; because it can be different in dump and mysql -v output
        $insertQuery = rtrim($insertQuery, "\n;");

        $columnsCount = is_object($tablesInfo) ? $tablesInfo->$tableName->columnsCount : $tablesInfo[$tableName]['columnsCount'];
        $textColumnsCount = is_object($tablesInfo) ? $tablesInfo->$tableName->textColumnsCount : $tablesInfo[$tableName]['textColumnsCount'];

        $insertRowsCount = 1 + substr_count($insertQuery, '),(', 11);
        $dataSize =
            strlen($insertQuery)
            - strpos($insertQuery, '(') // all before first brace
            - 3 // minus first brace and last braces
            - ($insertRowsCount - 1) * 3 // minus all row separators "),("
            - ($columnsCount - 1) * $insertRowsCount // minus all cell separators ","
            - $textColumnsCount * $insertRowsCount * 2 // minus all quotes for text values
        ;

        return $dataSize;
    }

}
