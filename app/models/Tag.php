<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

/**
* Tag Model
* Handles database operations for tags.
*/
class Tag extends Model
{
    protected string $table = 'tags';

    /**
    * Fetches a single tag by its ID.
    * @param int $id The ID of the tag.
    * @return array|null Tag data if found, null otherwise.
    */
    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }catch (PDOException $e) {
            error_log("Error fetching tag by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
    * Fetches a single tag by its slug.
    * @param string $slug The slug of the tag.
    * @return array|null Tag data if found, null otherwise.
    */
    public function findBySlug(string $slug): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE slug = :slug");
            $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }catch (PDOException $e) {
            error_log("Error fetching tag by slug: " . $e->getMessage());
            return null;
        }
    }

    /**
    * Fetches all tags, optionally limited.
    * @param int|null $limit Maximum number of tags to return.
    * @return array List of tag data.
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
            error_log("Error fetching all tags: " . $e->getMessage());
            return [];
        }
    }

    // --- Basic CRUD operations (for admin panel, but defined here for completeness) ---

    /**
    * Creates a new record.
    * @param array $data Associative array of tag data (e.g., 'name', 'slug', 'color', 'is_active').
    * @return int|false Inserted ID on success, false on failure.
    */
    public function create(array $data): int|false
    {
        if (empty($data['name']) || empty($data['slug'])) {
            return false;
        }

        $query = "INSERT INTO {$this->table} (name, slug, color, is_active) 
                  VALUES (:name, :slug, :color, :is_active)";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':name', $data['name']);
            $stmt->bindValue(':slug', $data['slug']);
            $stmt->bindValue(':color', $data['color'] ?? '#000000'); // Default color if not provided
            $stmt->bindValue(':is_active', $data['is_active'] ?? true, PDO::PARAM_BOOL); // Bind is_active
            
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        }catch (PDOException $e) {
            error_log("Error creating tag: " . $e->getMessage());
            return false;
        }                  
    }

    /**
    * Updates an existing record.
    * @param mixed $id The ID of the record to update.
    * @param array $data Associative array of tag data.
    * @return bool True on success, false on failure.
    */
    public function update($id, array $data): bool
    {
        $setClauses = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'slug', 'color', 'is_active'])) { // Include is_active here
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
        } catch (PDOException $e) {
            error_log("Error updating tag: " . $e->getMessage());
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
        } catch (PDOException $e) {
            error_log("Error deleting tag: " . $e->getMessage());
            return false;
        }
    }
}
