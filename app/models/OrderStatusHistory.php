<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class OrderStatusHistory extends Model
{
    protected string $table = 'order_status_history';
    
    public function __construct()
    {
        parent::__construct();
    }

    /**
    * Adds a new status entry to the order status history.
    * @param int $orderId The ID of the order.
    * @param string $status The new status of the order.
    * @param string|null $comment Optional comment for the status change.
    * @param int|null $createdByUserId The ID of the user who made the change (e.g., admin, user). Null if system.
    * @return int|false The ID of the newly created history entry on success, false on failure.
    */
    public function addStatusEntry(int $orderId, string $status, ?string $comment = null, ?int $createdByUserId = null): int|false
    {
        try {
            $data = [
                'order_id' => $orderId,
                'status' => $status,
                'comment' => $comment,
                'created_by' => $createdByUserId // Matches 'created_by' column in your table
            ];

            return $this->create($data);
        }catch (PDOException $e) {
            error_log("Error adding order status history entry: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Retrieves the status history for a specific order.
    *
    * @param int $orderId The ID of the order.
    * @param string $orderBy Column to order by (e.g., 'created_at').
    * @param string $sortOrder Sort order ('ASC' or 'DESC').
    * @return array An array of status history entries.
    */
    public function getHistoryForOrder(int $orderId, string $orderBy = 'created_at', string $sortOrder = 'ASC'): array
    {
        try {
            $query = "SELECT osh.*, u.username, u.first_name, u.last_name 
                      FROM {$this->table} osh
                      LEFT JOIN users u ON osh.created_by = u.id
                      WHERE osh.order_id = :order_id
                      ORDER BY osh.{$orderBy} {$sortOrder}";
            
            $result = $this->query($query, [':order_id' => $orderId], true);
            return is_array($result) ? $result : [];
        } catch (PDOException $e) {
            error_log("Error fetching order status history: " . $e->getMessage());
            return [];
        }
    } 
}
