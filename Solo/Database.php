<?php declare(strict_types=1);

namespace Solo;

use Solo\Database\DatabaseCore;
use PDOStatement;

class Database extends DatabaseCore
{
    /**
     * @param $sql
     * @param ...$parameters
     * @return false|PDOStatement
     */
    public function query($sql, ...$parameters)
    {
        $query = $this->prepare($sql, ...$parameters);
        return $this->pdoStatement = $this->pdo->query($query);
    }

    public function prepare($query, ...$parameters): string
    {
        if (empty($parameters)) return $query;
        $parsed_query = '';
        $array = preg_split('~(\?[sifaAtp])~u', $query, 0, PREG_SPLIT_DELIM_CAPTURE);
        $parameters_num = count($parameters);
        $placeholders_num = floor(count($array) / 2);
        if ($placeholders_num != $parameters_num) {
            $this->error("Number of args ($parameters_num) doesn't match number of placeholders ($placeholders_num) in [$query]");
        }
        foreach ($array as $i => $part) {
            if (($i % 2) == 0) {
                $parsed_query .= $part;
                continue;
            }

            $value = array_shift($parameters);
            switch ($part) {
                case '?s':
                    $part = $this->pdo->quote($value);
                    break;
                case '?i':
                    $part = is_int($value) ? $value : (int)$value;
                    break;
                case '?f':
                    $part = is_float($value) ? $value : floatval(str_replace(',', '.', $value));
                    break;
                case '?a':
                    if (!is_array($value)) {
                        $this->error("?a placeholder expects array, " . gettype($value) . " given");
                    }
                    foreach ($value as &$v) {
                        $v = is_int($v) ? $v : $this->pdo->quote($v);
                    }
                    $part = implode(',', $value);
                    break;
                case '?A':
                    if (is_array($value) && $value !== array_values($value)) {
                        foreach ($value as $key => &$v) {
                            $v = '`' . $key . '`=' . (is_int($v) ? $v : $this->pdo->quote($v));
                        }
                        $part = implode(', ', $value);
                    } else {
                        $this->error("?A placeholder expects Associative array, " . gettype($value) . " given");
                    }
                    break;
                case '?t':
                    $part = '`' . $value . '`';
                    break;
                case '?p':
                    $part = $value;
                    break;
            }
            $parsed_query .= $part;
        }
        return $parsed_query;
    }

    /** Fetches all rows from a result set and return as array of objects.
     * If $primaryKey return associative array of objects
     * @return array|false
     */
    public function results($primaryKey = '')
    {
        $results = $this->pdoStatement->fetchAll($this->pdo::FETCH_CLASS);
        if (!empty($primaryKey)) {
            $associativeResults = array();
            foreach ($results as $row) {
                $associativeResults[$row->$primaryKey] = $row;
            }
            return $associativeResults;
        } else {
            return $results;
        }
    }

    /** Fetches one row and returns it as an object
     * If $column is given, returns a single column of a result set
     */
    public function result($column = null)
    {
        if ($column) {
            $data = $this->pdoStatement->fetch();
            if (isset($data[$column])) {
                return $data[$column];
            } else {
                $this->error("$column is not present in result set");
            }
        } else {
            return $this->pdoStatement->fetchObject();
        }
    }

    /**
     * @return string|false
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /** Affected rows
     */
    public function rowCount(): int
    {
        return $this->pdoStatement->rowCount();
    }
}