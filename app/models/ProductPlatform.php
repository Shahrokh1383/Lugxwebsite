<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

/**
* ProductPlatform Model (Pivot Table)
* Manages the many-to-many relationship between products and platforms.
*/
class ProductPlatform extends Model
{
    protected string $table = 'product_platforms';
    protected string $primaryKey = 'product_id';

     /**
     * Attaches platforms to a product.
     * @param int $productId The ID of the product.
     * @param array $platformIds An array of platform IDs to attach.
     * @return bool True on success, false on failure.
     */
    public function attachPlatforms(int $productId, array $platformIds): bool
    {
        // Start a transaction to ensure atomicity
        $this->db->beginTransaction();
        try {
            // First, remove existing associations for the product
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();

            if (empty($platformIds)) {
                $this->db->commit();
                return true;
            }

            // Prepare for batch insert
            $placeholders = [];
            $params = [];
            foreach ($platformIds as $platformId) {
                $placeholders[] = "(?, ?)"; // product_id, platform_id
                $params[] = $productId;
                $params[] = $platformId;
            }

            $query = "INSERT INTO {$this->table} (product_id, platform_id) VALUES " . implode(', ', $placeholders);
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $this->db->commit();
            return true;
        }catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error attaching platforms to product: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Detaches all platforms from a product.
    * @param int $productId The ID of the product.
    * @return bool True on success, false on failure.
    */
    public function detachAllPlatforms(int $productId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            return $stmt->execute();
        }catch (PDOException $e) {
            error_log("Error detaching all platforms from product: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Get platforms associated with a specific product.
    * @param int $productId
    * @return array
    */
    public function getByProductId(int $productId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT platform_id FROM {$this->table} WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch (PDOException $e) {
            error_log("Error fetching product platforms by product ID: " . $e->getMessage());
            return [];
        }
    }
}
