<?php
namespace App\Core;

use PDO;
use PDOException;

class Model
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a record by its primary key.
     *
     * @param mixed $id
     * @return array|false
     */
    public function find($id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Get all records from the table.
     *
     * @return array
     */
    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll();
    }

    /**
     * Insert a new record into the table.
     *
     * @param array $data
     * @return int|false Last inserted ID or false on failure
     */
    public function create(array $data): int|false
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);

        try {
            $stmt->execute($data);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            // Log the error for debugging
            error_log("Error creating record in {$this->table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing record in the table.
     *
     * @param mixed $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data): bool
    {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $set = implode(', ', $set);

        $sql = "UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);

        $data['id'] = $id; // Add ID to data for binding
        try {
            return $stmt->execute($data);
        } catch (PDOException $e) {
            error_log("Error updating record in {$this->table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a record from the table.
     *
     * @param mixed $id
     * @return bool
     */
    public function delete($id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
        try {
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("Error deleting record from {$this->table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get records based on a WHERE clause.
     *
     * @param array $conditions Example: ['column' => 'value', 'another_column >' => 10]
     * @param string $orderBy
     * @param string $limit
     * @param string $offset
     * @return array
     */
    public function where(array $conditions, string $orderBy = '', string $limit = '', string $offset = ''): array
    {
        $whereClauses = [];
        $params = [];
        foreach ($conditions as $key => $value) {
            // Handle operators like '>', '<', '!=', 'LIKE'
            if (preg_match('/(.*?)\s*([<>=!%]|LIKE|NOT LIKE|IN|NOT IN|IS NULL|IS NOT NULL)$/i', $key, $matches)) {
                $column = trim($matches[1]);
                $operator = trim($matches[2]);
                if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                    $whereClauses[] = "{$column} {$operator}";
                } else if ($operator === 'IN' || $operator === 'NOT IN') {
                    // For IN/NOT IN, value should be an array
                    if (is_array($value)) {
                        $placeholders = implode(', ', array_fill(0, count($value), '?'));
                        $whereClauses[] = "{$column} {$operator} ({$placeholders})";
                        $params = array_merge($params, $value);
                    } else {
                        error_log("Invalid value for IN/NOT IN condition: " . $key);
                        continue;
                    }
                } else {
                    $whereClauses[] = "{$column} {$operator} :{$column}";
                    $params[":{$column}"] = $value;
                }
            } else {
                // Default to '=' operator
                $whereClauses[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        $sql = "SELECT * FROM {$this->table}" .
               (!empty($whereClauses) ? " WHERE " . implode(' AND ', $whereClauses) : "") .
               ($orderBy ? " ORDER BY {$orderBy}" : "") .
               ($limit ? " LIMIT {$limit}" : "") .
               ($offset ? " OFFSET {$offset}" : "");

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get a single record based on a WHERE clause.
     *
     * @param array $conditions
     * @return array|false
     */
    public function first(array $conditions): array|false
    {
        $results = $this->where($conditions, '', 1);
        return $results ? $results[0] : false;
    }

    /**
     * Execute a raw SQL query. Use with caution.
     *
     * @param string $sql
     * @param array $params
     * @param bool $fetch single row or all rows
     * @return array|false|int
     */
    public function query(string $sql, array $params = [], bool $fetchAll = true): array|false|int
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            if (str_starts_with(strtoupper(trim($sql)), 'SELECT')) {
                return $fetchAll ? $stmt->fetchAll() : $stmt->fetch();
            }
            return $stmt->rowCount(); // For INSERT, UPDATE, DELETE
        } catch (PDOException $e) {
            error_log("Raw query failed: " . $e->getMessage() . " SQL: " . $sql);
            return false;
        }
    }
}
