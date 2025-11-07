<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

/**
* ProductTag Model (Pivot Table)
* Manages the many-to-many relationship between products and tags.
*/
class ProductTag extends Model
{
    protected string $table = 'product_tags';
    protected string $primaryKey = 'product_id';

    /**
    * Attaches tags to a product.
    * @param int $productId The ID of the product.
    * @param array $tagIds An array of tag IDs to attach.
    * @return bool True on success, false on failure.
    */
    public function attachTags(int $productId, array $tagIds): bool
    {
        // Start a transaction to ensure atomicity
        $this->db->beginTransaction();
        try {
            // First, remove existing associations for the product
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();

            if (empty($tagIds)) {
                $this->db->commit();
                return true;
            }

             // Prepare for batch insert
            $placeholders = [];
            $params = [];
            foreach ($tagIds as $tagId) {
                $placeholders[] = "(?, ?)"; // product_id, tag_id
                $params[] = $productId;
                $params[] = $tagId;
            }

            $query = "INSERT INTO {$this->table} (product_id, tag_id) VALUES " . implode(', ', $placeholders);
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $this->db->commit();
            return true;
        }catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error attaching tags to product: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Detaches all tags from a product.
    * @param int $productId The ID of the product.
    * @return bool True on success, false on failure.
    */
    public function detachAllTags(int $productId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error detaching all tags from product: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Get tags associated with a specific product.
    * @param int $productId
    * @return array
    */
    public function getByProductId(int $productId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT tag_id FROM {$this->table} WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch (PDOException $e) {
            error_log("Error fetching product tags by product ID: " . $e->getMessage());
            return [];
        }
    }
}
