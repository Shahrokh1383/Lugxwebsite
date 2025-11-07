<?php
namespace App\Models;

use App\Core\Model;
use Exception;
use PDO;
use PDOException;

class Order extends Model
{
    protected string $table = 'orders';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Creates a new order record in the database.
     *
     * @param array $data An associative array of order data (e.g., user_id, total_amount, payment_method, billing_address, shipping_address, etc.).
     * @return int|false The ID of the newly created order on success, false on failure.
     */
    public function createOrder(array $data): int|false
    {
        try {
            if (isset($data['billing_address']) && is_array($data['billing_address'])) {
                $data['billing_address'] = json_encode($data['billing_address']);
            }
            if (isset($data['shipping_address']) && is_array($data['shipping_address'])) {
                $data['shipping_address'] = json_encode($data['shipping_address']);
            }

            return $this->create($data);
        }catch (PDOException $e) {
            error_log("Error creating order: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds an order by its ID.
     *
     * @param int $orderId The ID of the order.
     * @return array|null The order data if found, null otherwise.
     */
    public function findById(int $orderId): ?array
    {
        try {
            $order = $this->find($orderId);
            if ($order) {
                // Decode JSON fields
                if (isset($order['billing_address']) && is_string($order['billing_address'])) {
                    $order['billing_address'] = json_decode($order['billing_address'], true);
                }
                if (isset($order['shipping_address']) && is_string($order['shipping_address'])) {
                    $order['shipping_address'] = json_decode($order['shipping_address'], true);
                }
            }
            return $order ?: null;
        }catch (PDOException $e) {
            error_log("Error finding order by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find an order by its ID and fetch all related details.
     * This method joins with the 'users' and 'order_items' tables to provide a complete view.
     * It also fetches product details for each order item.
     *
     * @param int $orderId The ID of the order.
     * @return array|null The complete order data with user and item details, or null if not found.
     */
    public function findByIdWithDetails(int $orderId): ?array
    {
        try {
            $query = "
                SELECT 
                    o.*, 
                    u.first_name, 
                    u.last_name, 
                    u.email
                FROM {$this->table} o
                JOIN users u ON o.user_id = u.id
                WHERE o.id = :order_id
            ";

            $order = $this->query($query, [':order_id' => $orderId], false); // false for fetch

            if ($order) {
                // Decode JSON fields
                if (isset($order['billing_address']) && is_string($order['billing_address'])) {
                    $order['billing_address'] = json_decode($order['billing_address'], true);
                }
                if (isset($order['shipping_address']) && is_string($order['shipping_address'])) {
                    $order['shipping_address'] = json_decode($order['shipping_address'], true);
                }
                // Fetch order items and add to the order array
                $order['order_items'] = $this->getOrderItems($orderId);
            }

            return $order;
        }catch (PDOException $e) {
            error_log("Error fetching order with details: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get a single order item by its ID.
     *
     * @param int $itemId The ID of the order item.
     * @return array|null The order item data if found, null otherwise.
     */
    public function getOrderItemById(int $itemId): ?array
    {
        try {
            $query = "
                SELECT 
                    oi.*,
                    p.id AS product_id
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.id = :item_id
            ";

            $result = $this->query($query, [':item_id' => $itemId], false); // false for fetch
            return is_array($result) ? $result : null;
        }catch (PDOException $e) {
            error_log("Error fetching order item by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Updates the product_key_id for a specific order item.
     *
     * @param int $itemId The ID of the order item.
     * @param int $keyId The ID of the product key to assign.
     * @return bool True on success, false on failure.
     */
    public function updateOrderItemKey(int $itemId, int $keyId): bool
    {
        try {
            $query = "UPDATE order_items SET product_key_id = :key_id WHERE id = :item_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':key_id', $keyId, PDO::PARAM_INT);
            $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
            return $stmt->execute();
        }catch (PDOException $e) {
            error_log("Error updating order item key: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds all orders for a specific user.
     *
     * @param int $userId The ID of the user.
     * @param string $orderBy Column to order by.
     * @param string $sortOrder Sort order ('ASC' or 'DESC').
     * @return array An array of order data.
     */
    public function findByUser(int $userId, string $orderBy = 'created_at', string $sortOrder = 'DESC'): array
    {
        try {
            $orders = $this->where(['user_id' => $userId], "{$orderBy} {$sortOrder}");

            // Decode JSON fields for each order
            foreach ($orders as &$order) {
                if (isset($order['billing_address']) && is_string($order['billing_address'])) {
                    $order['billing_address'] = json_decode($order['billing_address'], true);
                }
                if (isset($order['shipping_address']) && is_string($order['shipping_address'])) {
                    $order['shipping_address'] = json_decode($order['shipping_address'], true);
                }
            }
            unset($order);

            return $orders;
        }catch (PDOException $e) {
            error_log("Error finding orders by user: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Updates the status of an order.
     *
     * @param int $orderId The ID of the order.
     * @param string $status The new status (e.g., 'processing', 'delivered').
     * @return bool True on success, false on failure.
     */
    public function updateStatus(int $orderId, string $status): bool
    {
        try {
            // Using the base Model's 'update' method
            $data = ['status' => $status];
            return $this->update($orderId, $data);
        } catch (PDOException $e) {
            error_log("Error updating order status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets all order items associated with a specific order.
     * Joins with the products table to fetch product information.
     *
     * @param int $orderId The ID of the order.
     * @return array An array of order items with product details.
     */
    public function getOrderItems(int $orderId): array
    {
        try {
            $query = "SELECT 
                                oi.id AS order_item_id, 
                                oi.product_id, 
                                oi.quantity, 
                                oi.price AS item_price_at_purchase, 
                                oi.total AS item_total_at_purchase,
                                oi.download_link,
                                p.title AS product_title, 
                                p.slug AS product_slug, 
                                p.featured_image AS product_featured_image,
                                p.price AS current_product_price, -- Current price from products table
                                p.sale_price AS current_product_sale_price,
                                p.key_count AS product_key_count -- ADDED: Fetch key_count from products table
                              FROM order_items oi
                              JOIN products p ON oi.product_id = p.id
                              WHERE oi.order_id = :order_id
                              ORDER BY oi.created_at ASC";
            
            // Using the base Model's 'query' method for complex joins
            $result = $this->query($query, [':order_id' => $orderId], true); // true for fetchAll
            return is_array($result) ? $result : []; // Ensure array is returned
        } catch (PDOException $e) {
            error_log("Error fetching order items: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Checks if a specific user has purchased a specific product.
     * Considers only 'completed' or 'delivered' orders.
     *
     * @param int $userId The ID of the user.
     * @param int $productId The ID of the product.
     * @return bool True if the user has purchased the product, false otherwise.
     */
    public function hasUserPurchasedProduct(int $userId, int $productId): bool
    {
        try {
            $query = "SELECT COUNT(oi.id)
                      FROM order_items oi
                      JOIN orders o ON oi.order_id = o.id
                      WHERE o.user_id = :user_id
                        AND oi.product_id = :product_id
                        AND o.status IN ('completed', 'delivered') -- Only consider completed/delivered orders
                      LIMIT 1"; // We only need to know if at least one purchase exists

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();

            return (bool) $stmt->fetchColumn(); // Returns true if count > 0, false otherwise
        } catch (PDOException $e) {
            error_log("Error checking user purchase status for product {$productId} by user {$userId}: " . $e->getMessage());
            return false; // Assume not purchased on error
        }
    }

    /**
     * Fetches the total number of orders.
     * @return int The total count of orders.
     */
    public function countAll(): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table}");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        }catch (PDOException $e) {
            error_log("Error counting all orders: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetches the most recent orders.
     * @param int $limit The number of recent orders to fetch.
     * @return array An array of recent order data.
     */
    public function findLast(int $limit = 5): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT :limit");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON fields for each order
            foreach ($orders as &$order) {
                if (isset($order['billing_address']) && is_string($order['billing_address'])) {
                    $order['billing_address'] = json_decode($order['billing_address'], true);
                }
                if (isset($order['shipping_address']) && is_string($order['shipping_address'])) {
                    $order['shipping_address'] = json_decode($order['shipping_address'], true);
                }
            }
            unset($order); // Unset the reference

            return $orders;
        } catch (PDOException $e) {
            error_log("Error fetching recent orders: " . $e->getMessage());
            return [];
        }
    }
}
