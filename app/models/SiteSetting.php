<?php
namespace App\Models;
use App\Core\Model;
use PDO;
use PDOException;
use Exception;

/**
 * SiteSetting Model
 * Handles database operations for site settings.
 */
class SiteSetting extends Model
{
    protected string $table = 'site_settings';
    
    /**
     * Fetches a single setting by its key.
     * @param string $key The key of the setting.
     * @return array|null Setting data if found, null otherwise.
     */
    public function findByKey(string $key): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE key_name = :key");
            $stmt->bindParam(':key', $key, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching setting by key: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Fetches all settings, optionally by group.
     * @param string|null $group The group name to filter by.
     * @return array List of setting data.
     */
    public function getAll(?string $group = null): array
    {
        try {
            $query = "SELECT * FROM {$this->table}";
            $params = [];
            
            if ($group) {
                $query .= " WHERE group_name = :group";
                $params[':group'] = $group;
            }
            
            $query .= " ORDER BY group_name ASC, key_name ASC";
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetches all autoload settings.
     * @return array List of autoload settings.
     */
    public function getAutoloadSettings(): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE is_autoload = TRUE");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert to key-value pairs
            $result = [];
            foreach ($settings as $setting) {
                $result[$setting['key_name']] = $this->castValue($setting['value'], $setting['type']);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error fetching autoload settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Creates or updates a setting.
     * @param array $data Associative array of setting data.
     * @return bool True on success, false on failure.
     */
    public function save(array $data): bool
    {
        // Validate required fields
        if (empty($data['key_name'])) {
            return false;
        }
        
        // Check if setting exists
        $existing = $this->findByKey($data['key_name']);
        
        if ($existing) {
            // Update existing setting
            return $this->update($existing['id'], $data);
        } else {
            // Create new setting
            return $this->create($data) > 0;
        }
    }
    
    /**
     * Creates a new setting.
     * @param array $data Associative array of setting data.
     * @return int|false Inserted ID on success, false on failure.
     */
    public function create(array $data): int|false
    {
        $query = "INSERT INTO {$this->table} 
                 (key_name, value, type, group_name, description, is_autoload) 
                 VALUES 
                 (:key_name, :value, :type, :group_name, :description, :is_autoload)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':key_name', $data['key_name']);
            $stmt->bindValue(':value', $data['value'] ?? null);
            $stmt->bindValue(':type', $data['type'] ?? 'text');
            $stmt->bindValue(':group_name', $data['group_name'] ?? 'general');
            $stmt->bindValue(':description', $data['description'] ?? null);
            $stmt->bindValue(':is_autoload', $data['is_autoload'] ?? true, PDO::PARAM_BOOL);
            
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating setting: " . $e->getMessage());
            return false;
        }   
    }
    
    /**
     * Updates an existing setting.
     * @param mixed $id The ID of the setting to update.
     * @param array $data Associative array of setting data.
     * @return bool True on success, false on failure.
     */
    public function update($id, array $data): bool
    {
        $setClauses = [];
        $params = [':id' => $id];
        
        foreach($data as $key => $value) {
            if (in_array($key, ['key_name', 'value', 'type', 'group_name', 'description', 'is_autoload'])) {
                $setClauses[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }
        
        if (empty($setClauses)) {
            return false; // No fields to update
        }
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $val) {
                $paramType = PDO::PARAM_STR;
                if (is_int($val)) {
                    $paramType = PDO::PARAM_INT;
                } elseif (is_bool($val)) {
                    $paramType = PDO::PARAM_BOOL;
                }
                $stmt->bindValue($key, $val, $paramType);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating setting: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Deletes a setting.
     * @param mixed $id The ID of the setting to delete.
     * @return bool True on success, false on failure.
     */
    public function delete($id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting setting: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Casts a value to the appropriate type.
     * @param string $value The value to cast.
     * @param string $type The type to cast to.
     * @return mixed The cast value.
     */
    private function castValue($value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return (bool) $value;
            case 'number':
                return is_numeric($value) ? (strpos($value, '.') !== false ? (float) $value : (int) $value) : $value;
            case 'json':
                return is_string($value) ? json_decode($value, true) : $value;
            default:
                return $value;
        }
    }
    
    /**
     * Begin a database transaction.
     * @return bool True on success, false on failure.
     */
    public function beginTransaction(): bool
    {
        try {
            return $this->db->beginTransaction();
        } catch (PDOException $e) {
            error_log("Error beginning transaction: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Commit a database transaction.
     * @return bool True on success, false on failure.
     */
    public function commit(): bool
    {
        try {
            return $this->db->commit();
        } catch (PDOException $e) {
            error_log("Error committing transaction: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rollback a database transaction.
     * @return bool True on success, false on failure.
     */
    public function rollBack(): bool
    {
        try {
            return $this->db->rollBack();
        } catch (PDOException $e) {
            error_log("Error rolling back transaction: " . $e->getMessage());
            return false;
        }
    }
}