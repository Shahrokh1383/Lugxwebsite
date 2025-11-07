<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class Wishlist extends Model
{
    protected string $table = 'wishlists';

    public function __construct()
    {
        parent::__construct();
    }

    /**
    * Adds a product to the user's wishlist.
    * Handles the unique constraint: if the item already exists, it won't be added again.
    *
    * @param int $userId The ID of the user.
    * @param int $productId The ID of the product.
    * @return bool True on success (added or already exists), false on failure.
    */
    public function add(int $userId, int $productId): bool
    {
        try {
            // Check if the item already exists in the wishlist for this user
            $existingItem = $this->first([
                'user_id' => $userId,
                'product_id' => $productId
            ]);

            if ($existingItem) {
                return true;
            }

            // If item does not exist, insert a new one
            $data = [
                'user_id' => $userId,
                'product_id' => $productId
            ];
            // The 'create' method returns the lastInsertId on success, false on failure.
            // We cast to boolean to indicate success or failure of the operation.
            return (bool) $this->create($data);
        }catch (PDOException $e) {
            error_log("Error adding item to wishlist: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Removes a product from the user's wishlist.
    *
    * @param int $userId The ID of the user.
    * @param int $productId The ID of the product to remove.
    * @return bool True on success, false if item not found or on database error.
    */
    public function remove(int $userId, int $productId): bool
    {
        try {
            // Find the wishlist item to get its ID for deletion
            $itemToRemove =$this->first([
                'user_id' => $userId,
                'product_id' => $productId
            ]);

            if (!$itemToRemove) {
                // Item not found, so nothing to remove, consider it a success for the user's intent
                return true;
            }

            // Use the base Model's 'delete' method by primary key
            return $this->delete($itemToRemove['id']);
        }catch (PDOException $e) {
            error_log("Error removing item from wishlist: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Gets all wishlist contents for a specific user, including relevant product details.
    * Joins with the products table to fetch product information.
    *
    * @param int $userId The ID of the user.
    * @return array An array of wishlist items with product details.
    */
    public function getWishlistContents(int $userId): array
    {
        try {
            $query = "SELECT 
                        w.id AS wishlist_item_id, 
                        w.product_id, 
                        w.created_at AS added_to_wishlist_at,
                        p.title, 
                        p.slug, 
                        p.featured_image, 
                        p.price, 
                        p.sale_price,
                        p.stock_status,
                        p.key_count AS available_stock_keys,
                        p.status AS product_status,
                        p.average_rating,
                        p.reviews_count
                      FROM {$this->table} w
                      JOIN products p ON w.product_id = p.id
                      WHERE w.user_id = :user_id
                      ORDER BY w.created_at DESC";
            
            // Using the base Model's 'query' method for complex joins
            $result = $this->query($query, [':user_id' => $userId], true); // true for fetchAll
            return is_array($result) ? $result : []; // Ensure array is returned
        } catch (PDOException $e) {
            error_log("Error fetching wishlist contents: " . $e->getMessage());
            return [];
        }
    }
}
