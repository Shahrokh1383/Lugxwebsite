<?php
namespace App\Models;
use App\Core\Model;
use PDO;
use PDOException;
use Exception;

/**
 * Category Model
 * Handles database operations for categories.
 */
class Category extends Model
{
    protected string $table = 'categories';
    
    /**
     * Fetches a single category by its ID.
     * @param int $id The ID of the category.
     * @return array|null Category data if found, null otherwise.
     */
    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching category by ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Fetches a single category by its slug.
     * @param string $slug The slug of the category.
     * @return array|null Category data if found, null otherwise.
     */
    public function findBySlug(string $slug): ?array 
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE slug = :slug");
            $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching category by slug: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Fetches all categories (including inactive ones) for admin panel.
     * @return array List of category data.
     */
    public function all(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY sort_order ASC, name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all categories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetches all active categories, optionally limited.
     * @param int|null $limit Maximum number of categories to return.
     * @return array List of category data.
     */
    public function getAll(?int $limit = null): array
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE is_active = TRUE ORDER BY sort_order ASC, name ASC";
            if ($limit !== null) {
                $query .= " LIMIT :limit";
            }
            $stmt = $this->db->prepare($query);
            if ($limit !== null) {
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all categories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Creates a new record.
     * @param array $data Associative array of data.
     * @return int|false Inserted ID on success, false on failure.
     */
    public function create(array $data): int|false
    {
        // Validate required fields
        if (empty($data['name']) || empty($data['slug'])) {
            return false;
        }
        
        $query = "INSERT INTO {$this->table} 
                 (name, slug, description, image, parent_id, sort_order, is_active, meta_title, meta_description) 
                 VALUES 
                 (:name, :slug, :description, :image, :parent_id, :sort_order, :is_active, :meta_title, :meta_description)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':name', $data['name']);
            $stmt->bindValue(':slug', $data['slug']);
            $stmt->bindValue(':description', $data['description'] ?? null);
            $stmt->bindValue(':image', $data['image'] ?? null);
            $stmt->bindValue(':parent_id', $data['parent_id'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':sort_order', $data['sort_order'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':is_active', $data['is_active'] ?? true, PDO::PARAM_BOOL);
            $stmt->bindValue(':meta_title', $data['meta_title'] ?? null);
            $stmt->bindValue(':meta_description', $data['meta_description'] ?? null);
            
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating category: " . $e->getMessage());
            return false;
        }   
    }
    
    /**
     * Updates an existing record.
     * @param mixed $id The ID of the record to update.
     * @param array $data Associative array of data.
     * @return bool True on success, false on failure.
     */
    public function update($id, array $data): bool
    {
        $setClauses = [];
        $params = [':id' => $id];
        
        foreach($data as $key => $value) {
            if (in_array($key, ['name', 'slug', 'description', 'image', 'parent_id', 'sort_order', 'is_active', 'meta_title', 'meta_description'])) {
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
            error_log("Error updating category: " . $e->getMessage());
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
            error_log("Error deleting category: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fetches all categories with their hierarchy structure.
     * @return array Hierarchical category structure.
     */
    public function getHierarchy(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY sort_order ASC, name ASC");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Build hierarchical structure
            $tree = [];
            $categoriesById = [];
            
            foreach ($categories as $category) {
                $categoriesById[$category['id']] = $category;
                $categoriesById[$category['id']]['children'] = [];
            }
            
            foreach ($categories as $category) {
                if ($category['parent_id'] && isset($categoriesById[$category['parent_id']])) {
                    $categoriesById[$category['parent_id']]['children'][] = &$categoriesById[$category['id']];
                } else {
                    $tree[] = &$categoriesById[$category['id']];
                }
            }
            
            return $tree;
        } catch (PDOException $e) {
            error_log("Error fetching category hierarchy: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetches all top-level categories (parent_id = NULL).
     * @return array List of top-level categories.
     */
    public function getTopLevel(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM {$this->table} WHERE parent_id IS NULL ORDER BY sort_order ASC, name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching top-level categories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetches all child categories of a given parent.
     * @param int $parentId The parent category ID.
     * @return array List of child categories.
     */
    public function getChildren(int $parentId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE parent_id = :parent_id ORDER BY sort_order ASC, name ASC");
            $stmt->bindParam(':parent_id', $parentId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching child categories: " . $e->getMessage());
            return [];
        }
    }

    /**
    * Get the count of products associated with a category.
    * @param int $categoryId The category ID.
    * @return int The count of products.
     */
    public function getProductCount(int $categoryId): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM product_categories WHERE category_id = :category_id");
            $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        }catch (PDOException $e) {
            error_log("Error getting product count for category {$categoryId}: " . $e->getMessage());
            return 0;
        }
    }
}