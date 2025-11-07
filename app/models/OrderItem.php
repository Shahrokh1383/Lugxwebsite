<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class OrderItem extends Model
{
    protected string $table = 'order_items';

    public function __construct()
    {
        parent::__construct();
    }

    /**
    * Creates a new order item record in the database.
    *
    * @param array $data An associative array of order item data (e.g., order_id, product_id, quantity, price, total, download_link).
    * @return int|false The ID of the newly created order item on success, false on failure.
    */
    public function createOrderItem(array $data): int|false
    {
        try {
            return $this->create($data);
        }catch (PDOException $e) {
            error_log("Error creating order item: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Finds order items by order ID.
    * This method is a wrapper for the query already present in Order model's getOrderItems.
    * It's good to have it here for consistency if needed directly, but typically accessed via Order model.
    *
    * @param int $orderId The ID of the order.
    * @return array An array of order item data.
    */
    public function findByOrderId(int $orderId): array
    {
        try {
            // Using the base Model's 'where' method
            return $this->where(['order_id' => $orderId]);
        } catch (PDOException $e) {
            error_log("Error finding order items by order ID: " . $e->getMessage());
            return [];
        }
    }

    /**
    * Gets the product key associated with this order item, if any.
    * This method assumes a one-to-one relationship where product_keys.order_item_id links back here.
    *
    * @param int $orderItemId The ID of the order item.
    * @return array|null The product key data if found, null otherwise.
    */
    public function getProductKey(int $orderItemId): ?array
    {
        try {
            // This requires joining with the product_keys table.
            // Since this is a specific relationship, we'll write a direct query.
            $query = "SELECT pk.* FROM product_keys pk WHERE pk.order_item_id = :order_item_id LIMIT 1";
            $result = $this->query($query, [':order_item_id' => $orderItemId], false); // false for fetch single row
            return is_array($result) ? $result : null;
        } catch (PDOException $e) {
            error_log("Error fetching product key for order item: " . $e->getMessage());
            return null;
        }
    }
}
