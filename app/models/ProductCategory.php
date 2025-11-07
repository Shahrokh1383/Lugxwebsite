<?php
namespace App\Models;
use App\Core\Model;
use PDO;
use PDOException;
/**
* ProductCategory Model (Pivot Table)
* Manages the many-to-many relationship between products and categories.
*/
class ProductCategory extends Model
{
    protected string $table = 'product_categories';
    protected string $primaryKey = 'product_id';
    /**
    * Attaches categories to a product.
    * @param int $productId The ID of the product.
    * @param array $categoryIds An array of category IDs to attach.
    * @param int|null $primaryCategoryId The ID of the primary category, if any.
    * @return bool True on success, false on failure.
    */
    public function attachCategories(int $productId, array $categoryIds, ?int $primaryCategoryId = null): bool 
    {
        if (empty($categoryIds)) {
            return true;
        }
        // Start a transaction to ensure atomicity
        $this->db->beginTransaction();
        try {
            // First, remove existing associations for the product
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Prepare for batch insert
            $values = [];
            $params = [];
            
            foreach ($categoryIds as $categoryId) {
                $isPrimary = ($primaryCategoryId === $categoryId) ? 1 : 0;
                $values[] = "(?, ?, ?)";
                $params[] = $productId;
                $params[] = $categoryId;
                $params[] = $isPrimary;
            }
            
            $query = "INSERT INTO {$this->table} (product_id, category_id, is_primary) VALUES " . implode(', ', $values);
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error attaching categories to product: " . $e->getMessage());
            return false;
        }
    }
    /**
    * Detaches all categories from a product.
    * @param int $productId The ID of the product.
    * @return bool True on success, false on failure.
    */
    public function detachAllCategories(int $productId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error detaching all categories from product: " . $e->getMessage());
            return false;
        }
    }
    /**
    * Get categories associated with a specific product.
    * @param int $productId
    * @return array
    */
    public function getByProductId(int $productId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT category_id, is_primary FROM {$this->table} WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching product categories by product ID: " . $e->getMessage());
            return [];
        }
    }
}