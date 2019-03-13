<?php

class Database_Adapter
{

    protected $_link;
    /** @var  PDOStatement */
    protected $_result;
    protected $_prefix;
    protected $_dbname;
    protected $_dryrun;
    public $onQuery = null;
    protected $_foreign_key_checks = 1;

    public function __construct($host, $user, $pass, $database, $prefix = "", $dryrun = false)
    {

        $this->_link = new PDO("mysql:dbname=${database};host=${host}", $user, $pass);

        $this->_dbname = $database;
        $this->_prefix = $prefix;
        $this->_dryrun = $dryrun;

    }

    public function setForeignKeyChecks($checks)
    {
        $this->_foreign_key_checks = $checks;
    }

    public function getDBName()
    {
        return $this->_dbname;
    }

    public function query($sql)
    {
        $sql = str_replace('{{{prefix}}}', $this->_prefix, $sql);

        if (!$this->_foreign_key_checks) {
            $sql = trim($sql, ' ;');
            $sql = "SET FOREIGN_KEY_CHECKS = 0; {$sql}; SET FOREIGN_KEY_CHECKS = 1;";
        }

        if (is_callable($this->onQuery))
            call_user_func($this->onQuery, $sql);
        flush();
        if (!$this->_dryrun || strpos($sql, "SHOW") !== false || strpos($sql, "SELECT") !== false) { // dry run
            $queries = preg_split("/;+(?=([^'|^\\\']*['|\\\'][^'|^\\\']*['|\\\'])*[^'|^\\\']*[^'|^\\\']$)/", $sql);
            foreach ($queries as $query) {
                if (!trim($query))
                    continue;
                $r = $this->_link->query($query);

                if (strpos($query, "SHOW") !== false || strpos($query, "SELECT") !== false)
                    $this->_result = $r;
            }
        }
        return $this;
    }

    public function fetchColumn($index)
    {
        $column = array();
        foreach ($this->_result as $row) {
            $column[] = $row[$index];
        }
        return $column;
    }

    public function fetchAll()
    {
        return $this->_result->fetchAll();
    }

    public function getAffected()
    {
        if ($this->_result)
            return $this->_result->rowCount();
        return 0;
    }


}