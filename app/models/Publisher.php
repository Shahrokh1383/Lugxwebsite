<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

/**
 * Publisher Model
 * Handles database operations for publishers.
 */
class Publisher extends Model 
{
    protected string $table = 'publishers';

    /**
     * Searches for publishers by name.
     * @param string $query The search query string.
     * @return array List of matching publishers.
     */
    public function searchByName(string $query): array
    {
        try {
            $stmt = $this->db->prepare("SELECT id, name FROM {$this->table} WHERE name LIKE :query LIMIT 10");
            $likeQuery = "%" . $query . "%";
            $stmt->bindParam(':query', $likeQuery, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error searching publishers by name: " . $e->getMessage());
            return [];
        }
    }

    /**
    * Fetches a single publisher by its ID.
    * @param int $id The ID of the publisher.
    * @return array|null Publisher data if found, null otherwise.
    */
    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }catch (PDOException $e) {
            error_log("Error fetching publisher by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
    * Fetches a single publisher by its slug.
    * @param string $slug The slug of the publisher.
    * @return array|null Publisher data if found, null otherwise.
    */
    public function findBySlug(string $slug): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE slug = :slug");
            $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }catch (PDOException $e) {
            error_log("Error fetching publisher by slug: " . $e->getMessage());
            return null;
        }
    }

    /**
    * Fetches all active publishers, optionally limited.
    * @param int|null $limit Maximum number of publishers to return.
    * @return array List of publisher data.
    */
    public function getAll(?int $limit = null): array
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE is_active = TRUE ORDER BY name ASC";
            if ($limit !== null) {
                $query .= " LIMIT :limit";
            }
            $stmt = $this->db->prepare($query);
            if ($limit !== null) {
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch (PDOException $e) {
            error_log("Error fetching all publishers: " . $e->getMessage());
            return [];
        }
    }

    // --- Basic CRUD operations (for admin panel, but defined here for completeness) ---

    /**
    * Creates a new record.
    * @param array $data Associative array of publisher data (e.g., 'name', 'slug', 'description', 'logo', 'website').
    * @return int|false Inserted ID on success, false on failure.
    */
    public function create(array $data): int|false
    {
        if (empty($data['name']) || empty($data['slug'])) {
            return false;
        }

        $query = "INSERT INTO {$this->table} (name, slug, description, logo, website, is_active) 
                  VALUES (:name, :slug, :description, :logo, :website, :is_active)";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':name', $data['name']);
            $stmt->bindValue(':slug', $data['slug']);
            $stmt->bindValue(':description', $data['description'] ?? null);
            $stmt->bindValue(':logo', $data['logo'] ?? null);
            $stmt->bindValue(':website', $data['website'] ?? null);
            $stmt->bindValue(':is_active', $data['is_active'] ?? true, PDO::PARAM_BOOL);
            
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating publisher: " . $e->getMessage());
            return false;
        }
    }

    
    /**
    * Updates an existing record.
    * @param mixed $id The ID of the record to update.
    * @param array $data Associative array of publisher data.
    * @return bool True on success, false on failure.
    */
    public function update($id, array $data): bool
    {
        $setClauses = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'slug', 'description', 'logo', 'website', 'is_active'])) {
                $setClauses[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $query = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE id = :id";

        try {
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : (is_bool($val) ? PDO::PARAM_BOOL : PDO::PARAM_STR));
            }
            return $stmt->execute();
        }catch (PDOException $e) {
            error_log("Error updating publisher: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Deletes a record from the table.
    * @param mixed $id The ID of the record to delete.
    * @return bool True on success, false on failure.
    */
    public function delete($id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        }catch (PDOException $e) {
            error_log("Error deleting publisher: " . $e->getMessage());
            return false;
        }
    }
}
