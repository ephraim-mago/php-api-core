<?php

namespace Framework\Database\Query;

use BackedEnum;
use InvalidArgumentException;
use Framework\Database\ConnectionInterface;

class Builder
{
    /**
     * The database connection instance.
     *
     * @var \Framework\Database\ConnectionInterface
     */
    public $connection;

    /**
     * The current query value bindings.
     *
     * @var array
     */
    public $bindings = [
        'where' => [],
    ];

    /**
     * The table which the query is targeting.
     *
     * @var string
     */
    public $from;

    /**
     * The columns that should be returned.
     *
     * @var array|null
     */
    public $columns;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $wheres = [];

    /**
     * Whether to use write pdo for the select.
     *
     * @var bool
     */
    public $useWritePdo = false;

    /**
     * Create a new query builder instance.
     *
     * @param  \Framework\Database\ConnectionInterface  $connection
     * @return void
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array|mixed  $columns
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param  string  $table
     * @param  string|null  $as
     * @return $this
     */
    public function from($table, $as = null)
    {
        $this->from = $as ? "{$table} as {$as}" : $table;

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $type
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $type = 'and')
    {
        if (is_null($value)) {
            $value = $operator;
            $operator = "=";
        }

        $this->wheres[] = compact('type', 'column', 'operator', 'value');

        $this->addBinding($value, 'where');

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param  \Closure|string|array  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        if (is_null($value)) {
            $value = $operator;
            $operator = "=";
        }

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Alias to set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function take($value)
    {
        return $this->limit($value);
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function limit($value)
    {
        // $property = $this->unions ? 'unionLimit' : 'limit';
        $property = 'limit';

        if ($value >= 0) {
            $this->$property = !is_null($value) ? (int) $value : null;
        }

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array|string  $columns
    //  * @return \Framework\Support\Collection
     * @return array
     */
    public function get($columns = ['*'])
    {
        return $this->onceWithColumns($columns, function () {
            return $this->connection->select(
                $this->toSql(),
                $this->getBindings(),
                !$this->useWritePdo
            );
        });
    }

    /**
     * Execute the given callback while selecting the given columns.
     *
     * After running the callback, the columns are reset to the original value.
     *
     * @param  array  $columns
     * @param  callable  $callback
     * @return mixed
     */
    protected function onceWithColumns($columns, $callback)
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $result = $callback();

        $this->columns = $original;

        return $result;
    }

    /**
     * Insert new records into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case
        // so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        return $this->connection->insert(
            $this->compileInsert($this, $values),
            ...array_values($values)
        );
    }

    /**
     * Update records in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        $sql = $this->compileUpdate($this, $values);

        return $this->connection->update(
            $sql,
            array_values(
                array_merge($values, $this->getBindings())
            )
        );
    }

    /**
     * Delete records from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (!is_null($id)) {
            $this->where($this->from . '.id', '=', $id);
        }

        // $this->applyBeforeQueryCallbacks();

        return $this->connection->delete(
            $this->compileDelete($this),
            $this->getBindings()
        );
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @param  \Framework\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        $table = $query->from;

        if (empty($values)) {
            return "insert into {$table} default values";
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $columns = implode(
            ", ",
            array_keys(reset($values))
        );

        $parameters = implode(
            ", ",
            array_map(function ($record) {
                return ":{$record}";
            }, array_keys(reset($values)))
        );

        return "insert into $table ($columns) values ($parameters)";
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param  \Framework\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileUpdate(Builder $query, array $values)
    {
        $table = $query->from;

        $columns = implode(
            ", ",
            array_map(
                function ($key) {
                    return "$key = ?";
                },
                array_keys($values)
            )
        );

        $where = $this->compileWheres($query);

        return "update {$table} set {$columns} {$where}";
    }

    /**
     * Compile the "where" portions of the query.
     *
     * @param  \Framework\Database\Query\Builder  $query
     * @return string
     */
    public function compileWheres(Builder $query)
    {
        $sql = "";

        if (!empty($query->wheres)) {
            $sql .= "where";

            foreach ($query->wheres as $index => $where) {
                if ($index > 0) {
                    $sql .= " {$where['type']}";
                }

                $sql .= " {$where['column']} {$where['operator']} ?";
            }
        }

        return $sql;
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @param  \Framework\Database\Query\Builder  $query
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        $table = $query->from;

        $where = $this->compileWheres($query);

        return "delete from {$table} {$where}";
    }

    public function toSql()
    {
        $sql = "";

        if ($this->columns > 0) {
            $sql = "select " . implode(", ", $this->columns);
        }

        if (!is_null($from = $this->from)) $sql .= " from {$from}";

        if (!empty($this->wheres)) {
            $sql .= ' ' . $this->compileWheres($this);
        }

        return $sql;
    }

    /**
     * Get the current query value bindings in a flattened array.
     *
     * @return array
     */
    public function getBindings()
    {
        return value(...array_values($this->bindings));
    }

    /**
     * Add a binding to the query.
     *
     * @param  mixed  $value
     * @param  string  $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function addBinding($value, $type = 'where')
    {
        if (!array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        $this->bindings[$type][] = $this->castBinding($value);

        return $this;
    }

    /**
     * Cast the given binding value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function castBinding($value)
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * Get the database connection instance.
     *
     * @return \Framework\Database\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
