<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Order;
use App\Models\ProductKey;
use App\Models\OrderStatusHistory;
use App\Services\AuthService;
use App\Services\ValidationService;
use App\Core\Database;
use PDO;
use PDOException;
use Exception;

class AdminOrderController extends Controller
{
    private Order $orderModel;
    private ProductKey $productKeyModel;
    private OrderStatusHistory $orderStatusHistoryModel;
    private AuthService $authService;
    private ValidationService $validator;
    private PDO $db;
    
    // وضعیت‌های معتبر سفارش مطابق با ENUM دیتابیس
    private array $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    private array $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
    
    public function __construct()
    {
        $this->orderModel = new Order();
        $this->productKeyModel = new ProductKey();
        $this->orderStatusHistoryModel = new OrderStatusHistory();
        $this->authService = new AuthService();
        $this->validator = new ValidationService();
        $this->db = Database::getInstance();
    }
    
    //-------------------------------------------------------------
    // View Management
    //-------------------------------------------------------------
    /**
     * Renders the static HTML view for managing orders.
     * GET /admin/orders
     */
    public function index(): void
    {
        $this->renderHtmlView('frontend/admin/admin_orders.html');
    }
    
    //-------------------------------------------------------------
    // API Endpoints
    //-------------------------------------------------------------
    /**
     * Get a list of all orders.
     * GET /api/admin/orders
     */
    public function indexApi(): void
    {
        $this->validateAdminAccess();
        
        try {
            $orders = $this->orderModel->all();
            $this->renderApiJson($orders);
        } catch (Exception $e) {
            $this->handleError("Error fetching orders", $e);
        }
    }
    
    /**
     * Get details for a single order, including order items and user info.
     * GET /api/admin/orders/{id}
     * @param int $id
     */
    public function show(int $id): void
    {
        $this->validateAdminAccess();
        
        try {
            $order = $this->orderModel->findByIdWithDetails($id);
            if (!$order) {
                $this->renderApiJson(['error' => 'Order not found.'], 404);
                return;
            }
            $this->renderApiJson($order);
        } catch (Exception $e) {
            $this->handleError("Error fetching order details", $e);
        }
    }
    
    /**
     * Update the status of an order.
     * PUT /api/admin/orders/{id}/status
     * @param int $id
     */
    public function updateStatus(int $id): void
    {
        $this->validateAdminAccess();
        
        $data = $this->getJsonData();
        $newStatus = $data['status'] ?? null;
        $comment = $data['comment'] ?? null;
        
        // Validation rules
        $rules = [
            'status' => 'required|in:' . implode(',', $this->validStatuses),
        ];
        
        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }
        
        $this->db->beginTransaction();
        try {
            $order = $this->orderModel->findById($id);
            if (!$order) {
                $this->db->rollBack();
                $this->renderApiJson(['error' => 'Order not found.'], 404);
                return;
            }
            
            if ($this->orderModel->updateStatus($id, $newStatus)) {
                // ثبت تغییر وضعیت در تاریخچه
                $this->orderStatusHistoryModel->addStatusEntry(
                    $id,
                    $newStatus,
                    $comment,
                    $this->authService->getAuthenticatedUserId()
                );
                
                $this->db->commit();
                $this->renderApiJson(['message' => 'Order status updated successfully!']);
            } else {
                $this->db->rollBack();
                $this->renderApiJson(['error' => 'Failed to update order status.'], 500);
            }
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->handleError("Error updating order status", $e);
        }
    }
    
    /**
     * Update the payment status of an order.
     * PUT /api/admin/orders/{id}/payment-status
     * @param int $id
     */
    public function updatePaymentStatus(int $id): void
    {
        $this->validateAdminAccess();
        
        $data = $this->getJsonData();
        $newStatus = $data['payment_status'] ?? null;
        
        // Validation rules
        $rules = [
            'payment_status' => 'required|in:' . implode(',', $this->validPaymentStatuses),
        ];
        
        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }
        
        try {
            $order = $this->orderModel->findById($id);
            if (!$order) {
                $this->renderApiJson(['error' => 'Order not found.'], 404);
                return;
            }
            
            if ($this->orderModel->update($id, ['payment_status' => $newStatus])) {
                $this->renderApiJson(['message' => 'Payment status updated successfully!']);
            } else {
                $this->renderApiJson(['error' => 'Failed to update payment status.'], 500);
            }
        } catch (Exception $e) {
            $this->handleError("Error updating payment status", $e);
        }
    }
    
    /**
     * Assign a product key to an order item.
     * POST /api/admin/orders/item/{itemId}/key
     * @param int $itemId The ID of the order item.
     */
    public function assignKey(int $itemId): void
    {
        $this->validateAdminAccess();
        
        $data = $this->getJsonData();
        $key = $data['key'] ?? null;
        
        // Validation rules
        $rules = [
            'key' => 'required|max:255',
        ];
        
        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }
        
        $this->db->beginTransaction();
        try {
            // Find the order item and product
            $orderItem = $this->orderModel->getOrderItemById($itemId);
            if (!$orderItem) {
                $this->db->rollBack();
                $this->renderApiJson(['error' => 'Order item not found.'], 404);
                return;
            }
            
            $productId = $orderItem['product_id'] ?? null;
            if (!$productId) {
                $this->db->rollBack();
                $this->renderApiJson(['error' => 'Order item is not associated with a product.'], 404);
                return;
            }
            
            // Check if the key already exists for this product
            $existingKey = $this->productKeyModel->findByProductAndKeyForUpdate($productId, $key);
            
            if ($existingKey) {
                if ($existingKey['is_used']) {
                    $this->db->rollBack();
                    $this->renderApiJson(['error' => 'This key is already used.'], 409);
                    return;
                }
                
                // Mark existing key as used
                $result = $this->productKeyModel->markAsRedeemed($existingKey['id'], $itemId);
            } else {
                // Create a new key
                $keyId = $this->productKeyModel->create([
                    'product_id' => $productId,
                    'license_key' => $key,
                    'is_used' => true,
                    'order_item_id' => $itemId
                ]);
                
                $result = ($keyId !== false);
            }
            
            if ($result) {
                $this->db->commit();
                $this->renderApiJson(['message' => 'Product key assigned successfully!']);
            } else {
                $this->db->rollBack();
                $this->renderApiJson(['error' => 'Failed to assign product key.'], 500);
            }
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->handleError("Error assigning product key", $e);
        }
    }
    
    /**
     * Get status history for an order.
     * GET /api/admin/orders/{id}/status-history
     * @param int $id
     */
    public function getStatusHistory(int $id): void
    {
        $this->validateAdminAccess();
        
        try {
            $history = $this->orderStatusHistoryModel->getHistoryForOrder($id);
            $this->renderApiJson($history);
        } catch (Exception $e) {
            $this->handleError("Error fetching order status history", $e);
        }
    }
    
    //-------------------------------------------------------------
    // Helper Methods
    //-------------------------------------------------------------
    /**
     * Validate admin access and return error if unauthorized
     */
    private function validateAdminAccess(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            exit;
        }
    }
    
    /**
     * Handle errors consistently
     * @param string $logMessage
     * @param Exception $e
     */
    private function handleError(string $logMessage, Exception $e): void
    {
        error_log("$logMessage: " . $e->getMessage());
        $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
    }
}