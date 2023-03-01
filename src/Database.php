<?php

namespace Debva\Elnix;

class Database
{
    private $connection;

    private $driver;

    private $table;

    private $select = [];

    private $from;

    private $join = [];

    private $whereClause = [];

    private $orderBy;

    private $limit;

    private $offset;

    private $isCount = false;

    private $bindings = [];

    public function __construct($host, $port, $database, $user, $password, $driver = 'mysql')
    {
        if (!in_array($driver, $this->driverSupport())) {
            die("Driver {$driver} not supported");
        }

        $this->driver = $driver;
        $dsn = "{$driver}:host={$host}:{$port};dbname={$database}";

        try {
            $this->connection = new \PDO($dsn, $user, $password);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            die("Connection failed: {$e->getMessage()}");
        }
    }

    private function driverSupport()
    {
        return [
            'mysql',
            'pgsql'
        ];
    }

    private function operatorSupport()
    {
        $operators = [
            '<',
            '<=',
            '!<',
            '>',
            '>=',
            '!>',
            '<>',
            '!=',
            '=',
            'BETWEEN',
            'EXISTS',
            'OR',
            'AND',
            'NOT',
            'IN',
            'ALL',
            'ANY',
            'LIKE',
            'IS NULL',
            'UNIQUE',
        ];

        if ($this->driver === 'pgsql') {
            array_push($operators, 'ILIKE');
        }

        return $operators;
    }

    private function buildQuery($query = null)
    {
        $table = !empty($this->from) ? $this->from : $this->table;
        $select = empty($this->select) ? '*' : implode(', ', $this->select);

        if ($this->isCount) {
            $select = "COUNT(*) AS count";
        }

        if (is_null($query)) {
            $query = "SELECT {$select} FROM {$table}";
        }

        if (empty($table)) {
            die('Table not yet defined');
        }

        if (!empty($this->join)) {
            $query .= ' ' . implode(' ', $this->join);
        }

        if (!empty($this->whereClause)) {
            array_walk($this->whereClause, function ($where) use (&$operators, &$whereClause) {
                $operators[] = " {$where['operator']} ";
                $whereClause[] = $where['where'];
            });

            array_shift($operators);
            array_push($operators, null);

            $query .= ' WHERE ' . implode(' ', array_map(function ($where, $operator) {
                return trim("{$where}{$operator}");
            }, $whereClause, $operators));
        }

        if (!empty($this->orderBy)) {
            $query .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if (!empty($this->limit)) {
            $query .= " LIMIT {$this->limit}";
        }

        if (!empty($this->offset)) {
            $query .= " OFFSET {$this->offset}";
        }

        return $query;
    }

    private function buildBinding($statement)
    {
        if (!empty($this->bindings)) {
            foreach ($this->bindings as $key => $value) {
                $statement->bindValue($key, $value);
            }
        }
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollback()
    {
        return $this->connection->rollback();
    }

    public function toSql()
    {
        return $this->buildQuery();
    }

    public function table($table)
    {
        $this->table = sanitize_string($table);
        return $this;
    }

    public function select(...$columns)
    {
        if (is_array($columns)) {
            $columns = array_map(function ($column) {
                return sanitize_string($column);
            }, flatten($columns));
        }

        if (is_string($columns)) {
            $columns = sanitize_string($columns);
        }

        $this->select = $columns;
        return $this;
    }

    public function from($table)
    {
        $this->from = sanitize_string($table);
        return $this;
    }

    public function join($table, $localKey, $operator, $foreignKey, $type = 'INNER')
    {
        if (!in_array($operator = strtoupper($operator), $this->operatorSupport())) {
            die("Operator {$operator} not supported");
        }

        list($table, $localKey, $foreignKey) = sanitize_string($table, $localKey, $foreignKey);

        $this->join[] = "{$type} JOIN {$table} ON {$localKey} {$operator} {$foreignKey}";
        return $this;
    }

    public function leftJoin($table, $localKey, $operator, $foreignKey)
    {
        return $this->join($table, $localKey, $operator, $foreignKey, 'LEFT');
    }

    public function rightJoin($table, $localKey, $operator, $foreignKey)
    {
        return $this->join($table, $localKey, $operator, $foreignKey, 'RIGHT');
    }

    public function fullJoin($table, $localKey, $operator, $foreignKey)
    {
        return $this->join($table, $localKey, $operator, $foreignKey, 'FULL');
    }

    public function where($column, $operator, $value, $operatorWhereClause = 'AND')
    {
        if (!in_array($operator = strtoupper($operator), $this->operatorSupport())) {
            die("Operator {$operator} not supported");
        }

        $keyBinding = ":{$column}_" . strtolower(generate_string(5));
        $this->whereClause[] = [
            'operator' => $operatorWhereClause,
            'where' => "{$column} {$operator} {$keyBinding}"
        ];
        $this->bindings[$keyBinding] = $value;
        return $this;
    }

    public function whereIn($column, $value)
    {
        $this->where($column, 'IN', $value);
        return $this;
    }

    public function orWhere($column, $operator, $value)
    {
        $this->where($column, $operator, $value, 'OR');
        return $this;
    }

    public function orWhereIn($column, $value)
    {
        $this->orWhere($column, 'IN', $value);
        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        if (!in_array($direction = strtoupper($direction), ['ASC', 'DESC'])) {
            die('Direction not supported');
        }

        $this->orderBy[] = sanitize_string($column) . " {$direction}";
        return $this;
    }

    public function limit($limit)
    {
        if (!is_numeric($limit) || $limit < 0) {
            die('Limit must be numeric');
        }

        $this->limit = $limit;
        return $this;
    }

    public function offset($offset)
    {
        if (!is_numeric($offset) || $offset < 0) {
            die('Offset must be numeric');
        }

        $this->offset = $offset;
        return $this;
    }

    public function get()
    {
        $this->isCount = false;

        $statement = $this->connection->prepare($this->buildQuery());
        $this->buildBinding($statement);
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function count()
    {
        $this->isCount = true;

        $statement = $this->connection->prepare($this->buildQuery());
        $this->buildBinding($statement);
        $statement->execute();
        $result = $statement->fetch();

        return (int) $result['count'];
    }

    public function insert($data)
    {
        if (empty($data)) return true;

        $params = [];
        $columns = array_keys($data[0]);

        foreach ($data as $row) {
            $params = array_merge($params, array_values($row));
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        }

        $columns = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($data), "($placeholders)"));
        $query = $this->buildQuery("INSERT INTO {$this->table} ($columns) VALUES {$placeholders}");

        $statement = $this->connection->prepare($query);
        $this->buildBinding($statement);
        return $statement->execute($params);
    }

    public function update($data)
    {
        if (empty($data)) return true;

        if (!empty($this->bindings)) {
            $values = [];

            foreach ($data as $column => $value) {
                $keyBinding = ":{$column}_" . strtolower(generate_string(5));
                $this->bindings = array_slice($this->bindings, 0, 0, true) + [$keyBinding => $value] + array_slice($this->bindings, 0, NULL, true);
                $values[] = "{$column} = $keyBinding";
            }


            $values = implode(', ', $values);
            $query = $this->buildQuery("UPDATE {$this->table} SET {$values}");

            $statement = $this->connection->prepare($query);
            $this->buildBinding($statement);
            return $statement->execute();
        }

        die('Where clause is not defined');
    }

    public function delete()
    {
        $query = $this->buildQuery("DELETE FROM {$this->table}");
        $statement = $this->connection->prepare($query);
        $this->buildBinding($statement);
        return $statement->execute();
    }
}
