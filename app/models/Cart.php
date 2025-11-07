<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class Cart extends Model
{
    protected string $table = 'cart';

    public function __construct()
    {
        parent::__construct();
    }

    public function findByUserAndProduct(int $userId, int $productId): ?array
    {
        try {
            $item = $this->first([
                'user_id' => $userId,
                'product_id' => $productId
            ]);
            return $item ?: null;
        }catch (PDOException $e) {
            error_log("Error finding cart item by user and product: " . $e->getMessage());
            return null;
        }
    }

    public function addItem(int $userId, int $productId, int $quantity, float $price): bool
    {
        try {
            // Check if item already exists for this user and product
            $existingItem = $this->findByUserAndProduct($userId, $productId);
            if ($existingItem) {
                // If item exists, update its quantity
                $newQuantity = $existingItem['quantity'] + $quantity;
                return $this->updateItemQuantity($existingItem['id'], $newQuantity);
            } else {
                // If item does not exist, insert a new one
                // Using the base Model's 'create' method
                $data = [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $price // Ensure this is the price at the time of adding
                ];
                return (bool) $this->create($data); // create returns int|false, cast to bool
            }
        } catch (PDOException $e) {
            error_log("Error adding item to cart: " . $e->getMessage());
            return false;
        }
    }

    public function updateItemQuantity(int $cartId, int $quantity): bool
    {
        try {
            if ($quantity <= 0) {
                return $this->removeItem($cartId); // Remove the item if quantity is zero or negative
            }
            // Using the base Model's 'update' method
            $data = ['quantity' => $quantity];
            return $this->update($cartId, $data);
        } catch (PDOException $e) {
            error_log("Error updating cart item quantity: " . $e->getMessage());
            return false;
        }
    }

    public function removeItem(int $cartId): bool
    {
        try {
            return $this->delete($cartId);
        }catch (PDOException $e) {
            error_log("Error removing cart item: " . $e->getMessage());
            return false;
        }
    }

    public function getCartContents(int $userId): array
    {
        try {
            $query = "SELECT 
                        c.id AS cart_item_id, 
                        c.product_id, 
                        c.quantity, 
                        c.price AS cart_item_price, -- Price at the time of adding to cart
                        p.title, 
                        p.slug, 
                        p.featured_image, 
                        p.price AS current_product_price, -- Current price from products table
                        p.sale_price,
                        p.stock_status,
                        p.key_count AS available_stock_keys, -- Use key_count for available stock
                        p.status AS product_status
                      FROM {$this->table} c
                      JOIN products p ON c.product_id = p.id
                      WHERE c.user_id = :user_id
                      ORDER BY c.created_at DESC";
            // Using the base Model's 'query' method for complex joins
            $result = $this->query($query, [':user_id' => $userId], true); // true for fetchAll
            return is_array($result) ? $result : []; // Ensure array is returned
        } catch (PDOException $e) {
            error_log("Error fetching cart contents: " . $e->getMessage());
            return [];
        }
    }

    public function clearCart(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        }catch (PDOException $e) {
            error_log("Error clearing cart: " . $e->getMessage());
            return false;
        }
    }
}
