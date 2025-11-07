<?php
namespace App\Models;

use App\Core\Model;
use Exception;
use PDO;
use PDOException;

class ProductKey extends Model
{
    protected string $table = 'product_keys';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Retrieves an available (unused) license key for a specific product.
     * This method is crucial for assigning keys during order creation.
     *
     * @param int $productId The ID of the product.
     * @return array|null The available key data (id, license_key) if found, null otherwise.
     */
    public function getAvailableKey(int $productId): ?array
    {
        try {
            $query = "SELECT id, license_key FROM {$this->table} WHERE product_id = :product_id AND is_used = FALSE LIMIT 1 FOR UPDATE";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }catch (PDOException $e) {
            error_log("Error fetching available product key: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Marks a specific license key as used and associates it with an order item.
     * This operation should typically be part of a larger database transaction.
     * @param int $keyId The ID of the product key to mark as used.
     * @param int $orderItemId The ID of the order item this key is assigned to.
     * @return bool True on success, false on failure.
     */
    public function markAsRedeemed(int $keyId, int $orderItemId): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET is_used = TRUE, order_item_id = :order_item_id, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND is_used = FALSE");
            $stmt->bindParam(':order_item_id', $orderItemId, PDO::PARAM_INT);
            $stmt->bindParam(':id', $keyId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        }catch (PDOException $e) {
            error_log("Error marking product key as redeemed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds all product keys associated with a specific order item ID.
     * Useful for retrieving all keys assigned to a single order item.
     * @param int $orderItemId The ID of the order item.
     * @return array An array of product key data.
     */
    public function findKeysByOrderItemId(int $orderItemId): array // Changed method name and return type
    {
        try {
            // Removed LIMIT 1 to fetch all keys for the given order_item_id
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE order_item_id = :order_item_id");
            $stmt->bindParam(':order_item_id', $orderItemId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all results
        }catch (PDOException $e) {
            error_log("Error finding product keys by order item ID: " . $e->getMessage());
            return [];
        }
    }

    /**
     * NEW: Find a product key by its product ID and key value.
     *
     * @param int $productId The ID of the product.
     * @param string $keyValue The value of the key to search for.
     * @return array|null The product key data if found, null otherwise.
     */
    public function findByProductAndKey(int $productId, string $keyValue): ?array
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE product_id = :product_id AND license_key = :license_key LIMIT 1";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->bindParam(':license_key', $keyValue, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        }catch (PDOException $e) {
            error_log("Error finding product key: " . $e->getMessage());
            return null;
        }
    }

    /**
    * Find a product key by its product ID and key value with row lock.
    *
    * @param int $productId The ID of the product.
    * @param string $keyValue The value of the key to search for.
    * @return array|null The product key data if found, null otherwise.
    */
    public function  findByProductAndKeyForUpdate(int $productId, string $keyValue): ?array
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE product_id = :product_id AND license_key = :license_key FOR UPDATE";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->bindParam(':license_key', $keyValue, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        }catch (PDOException $e) {
            error_log("Error finding product key for update: " . $e->getMessage());
            return null;
        } 
    }
}
