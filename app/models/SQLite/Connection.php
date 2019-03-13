<?php

namespace SQLite;

class Connection extends \SQLite3
{

    public function query($sql)
    {
        if (!$result = @parent::query($sql)) {
            error('SQLite query error: ' . $this->lastErrorMsg());
        }
        return $result;
    }

    public function insert($table, $data)
    {
        $fields = $this->quoteArray(array_keys($data));
        $values = $this->quoteArray(array_values($data));
        $fieldsList = implode(', ', $fields);
        $valuesList = implode(', ', $values);
        $this->query("insert into $table ($fieldsList) values ($valuesList)");
    }

    public function quoteArray($array)
    {
        foreach ($array as &$value) {
            $value = $this->quote($value);
        }
        return $array;
    }

    public function quote($string)
    {
        return "'" . $this->escapeString($string) . "'";
    }

}
