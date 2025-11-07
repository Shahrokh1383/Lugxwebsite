<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

/**
* RelatedProduct Model (Pivot Table)
* Manages relationships between products (e.g., similar, cross-sell, up-sell).
*/
class RelatedProduct extends Model
{
    protected string $table = 'related_products';
    protected string $primaryKey = 'product_id';

    /**
    * Attaches related products to a product.
    * This method will first remove all existing related products for the given product_id,
    * then insert the new relationships.
    *
    * @param int $productId The ID of the main product.
    * @param array $relatedProductsData An array of associative arrays, each containing 'related_product_id' and 'relation_type'.
    * Example: [['related_product_id' => 10, 'relation_type' => 'similar'], ...]
    * @return bool True on success, false on failure.
    */
    public function attachRelatedProducts(int $productId, array $relatedProductsData): bool
    {
        $this->db->beginTransaction();
        try {
            // First, remove existing associations for the product
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();

            if (empty($relatedProductsData)) {
                $this->db->commit();
                return true;
            }

            // Prepare for batch insert
            $placeholders = [];
            $params = [];
            foreach ($relatedProductsData as $data) {
                $relatedProductId = $data['related_product_id'] ?? null;
                $relationType = $data['relation_type'] ?? 'similar'; // Default to 'similar'

                // Ensure related_product_id is not null and not the same as product_id
                if ($relatedProductId === null || $relatedProductId === $productId) {
                    error_log("Skipping invalid related product ID for product {$productId}: {$relatedProductId}");
                    continue; 
                }

                $placeholders[] = "(?, ?, ?)"; // product_id, related_product_id, relation_type
                $params[] = $productId;
                $params[] = $relatedProductId;
                $params[] = $relationType;
            }

            if (empty($placeholders)) {
                $this->db->commit();
                return true;
            }

            $query = "INSERT INTO {$this->table} (product_id, related_product_id, relation_type) VALUES " . implode(', ', $placeholders);
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $this->db->commit();
            return true;
        }catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error attaching related products to product: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Detaches all related products from a product.
    * @param int $productId The ID of the product.
    * @return bool True on success, false on failure.
    */
    public function detachAllRelatedProducts(int $productId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            return $stmt->execute();
        }catch (PDOException $e) {
            error_log("Error detaching all related products from product: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Get related products associated with a specific product.
    * @param int $productId The ID of the main product.
    * @param string|null $relationType Optional: Filter by relation type (e.g., 'similar', 'cross_sell', 'up_sell').
    * @return array List of related product IDs and their relation types.
    */
    public function getByProductId(int $productId, ?string $relationType = null): array
    {
        try {
            $query = "SELECT related_product_id, relation_type FROM {$this->table} WHERE product_id = :product_id";
            $params = [':product_id' => $productId];

            if ($relationType  !== null) {
                $query .= " AND relation_type = :relation_type";
                $params[':relation_type'] = $relationType;
            }

            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch (PDOException $e) {
            error_log("Error fetching related products by product ID: " . $e->getMessage());
            return [];
        }
    }
}
