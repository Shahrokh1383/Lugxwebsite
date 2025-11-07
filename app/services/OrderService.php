<?php
namespace App\Services;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product; // For updating product stock
use App\Models\ProductKey;
use App\Models\Coupon; // For incrementing coupon usage
use App\Models\OrderStatusHistory;
use App\Core\Database; // For transactions
use App\Services\CartService; // To get cart contents and clear cart
use App\Services\ValidationService;
use PDO;
use PDOException;
use Exception;
class OrderService
{
    private Order $orderModel;
    private OrderItem $orderItemModel;
    private Product $productModel;
    private ProductKey $productKeyModel;
    private Coupon $couponModel;
    private OrderStatusHistory $orderStatusHistoryModel;
    private CartService $cartService;
    private ValidationService $validationService;
    private $db;
    public function __construct()
    {
        $this->orderModel = new Order();
        $this->orderItemModel = new OrderItem();
        $this->productModel = new Product();
        $this->productKeyModel = new ProductKey();
        $this->couponModel = new Coupon();
        $this->orderStatusHistoryModel = new OrderStatusHistory();
        $this->cartService = new CartService();
        $this->validationService = new ValidationService();
        $this->db = Database::getInstance();
    }
    /**
     * Creates a new order from the user's cart.
     * This method performs several critical operations within a database transaction
     * to ensure atomicity (all or nothing).
     * @param int $userId The ID of the user placing the order.
     * @param array $checkoutData Associative array containing checkout details:
     * - 'payment_method': string (e.g., 'credit_card', 'paypal')
     * - 'coupon_code': string|null (optional)
     * - 'billing_address': array (JSON structure)
     * - 'shipping_address': array (JSON structure)
     * - 'notes': string|null (optional)
     * @return array Result array with 'status' (success/error), 'message', 'order_number' (if success), 'errors' (if validation fails).
     */
    public function createOrderFromCart(int $userId, array $checkoutData): array
    {
        $this->validationService->resetErrors(); // Reset errors for new validation
        // 1. Validate checkout data
        if (!$this->validationService->validate($checkoutData, [
            'payment_method' => 'required|string|in:credit_card,paypal,bank_transfer,crypto',
            'coupon_code' => 'nullable|string|max:50',
            'billing_address' => 'required', // Will validate structure later if needed
            'shipping_address' => 'required', // Will validate structure later if needed
            'notes' => 'nullable|string|max:1000'
        ])) {
            return ['status' => 'error', 'message' => 'Invalid checkout data provided.', 'errors' => $this->validationService->getErrors()];
        }
        $paymentMethod = $checkoutData['payment_method'];
        $couponCode = $checkoutData['coupon_code'] ?? null;
        
        // اگر کوپن در داده‌های چک‌اوت وجود نداشته باشد، از سشن دریافت می‌کنیم
        if (empty($couponCode) && isset($_SESSION['applied_coupon_code'])) {
            $couponCode = $_SESSION['applied_coupon_code'];
            error_log("DEBUG: OrderService::createOrderFromCart - Using coupon from session: {$couponCode}");
        }
        
        $billingAddress = $checkoutData['billing_address'];
        $shippingAddress = $checkoutData['shipping_address'];
        $notes = $checkoutData['notes'] ?? null;
        // Basic validation for address structures (can be expanded in ValidationService)
        if (!is_array($billingAddress) || empty($billingAddress) || !is_array($shippingAddress) || empty($shippingAddress)) {
             return ['status' => 'error', 'message' => 'Billing or shipping address is invalid.'];
        }
        // 2. Get cart contents and calculate totals (re-calculate on server-side for security)
        $cartItems = $this->cartService->getCart($userId);
        if (empty($cartItems)) {
            return ['status' => 'error', 'message' => 'Your cart is empty. Cannot create an order.'];
        }
        $calculatedTotals = $this->cartService->calculateCartTotals($userId, $couponCode);
        $subtotal = $calculatedTotals['subtotal'];
        $discountAmount = $calculatedTotals['discount'];
        $totalAmount = $calculatedTotals['total'];
        $appliedCoupon = $calculatedTotals['applied_coupon'];
        
        // 3. Final stock validation and key availability check BEFORE transaction begins
        foreach ($cartItems as $item) {
            $product = $this->productModel->getProductForCart($item['product_id']);
            if (!$product || $product['stock_status'] === 'out_of_stock' || $product['status'] !== 'published') {
                return ['status' => 'error', 'message' => "Product '{$item['title']}' is no longer available."];
            }
            // Check key_count only if product is digital (assuming key_count > 0 implies digital)
            if ($product['key_count'] > 0 && $product['key_count'] < $item['quantity']) {
                return ['status' => 'error', 'message' => "Not enough stock for '{$item['title']}'. Available: {$product['key_count']}."];
            }
        }
        // 4. Check per-user coupon limit (if coupon was applied)
        if ($appliedCoupon && $appliedCoupon['per_user_limit'] > 0) {
            $userCouponUsageCount = $this->getCouponUsageCountForUser($userId, $appliedCoupon['id']);
            if ($userCouponUsageCount >= $appliedCoupon['per_user_limit']) {
                return ['status' => 'error', 'message' => 'You have exceeded the usage limit for this coupon.'];
            }
        }
        // Generate a unique order number
        $orderNumber = $this->generateUniqueOrderNumber();
        // Start a database transaction
        $this->db->beginTransaction();
        try {
            // 5. Create the main order record
            $orderData = [
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'status' => 'pending', // Initial status
                'payment_status' => 'pending', // Initial payment status
                'payment_method' => $paymentMethod,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'coupon_code' => $appliedCoupon ? $appliedCoupon['code'] : null,
                'tax_amount' => 0.00, // Assuming 0 for now, can be calculated later
                'shipping_amount' => 0.00, // Assuming 0 for now, can be calculated later
                'total_amount' => $totalAmount,
                'billing_address' => $billingAddress,
                'shipping_address' => $shippingAddress,
                'notes' => $notes
            ];
            $orderId = $this->orderModel->createOrder($orderData);
            if (!$orderId) {
                throw new Exception('Failed to create order record.');
            }
            // 6. Add order items, assign product keys, and update product stock
            foreach ($cartItems as $item) {
                // Get fresh product data to ensure current price/stock
                $product = $this->productModel->getProductForCart($item['product_id']);
                if (!$product) {
                    throw new Exception("Product '{$item['title']}' not found during order item creation.");
                }
                // Determine the price to store in order_items (sale_price if applicable, otherwise regular price)
                // This is the price at the time of purchase, not necessarily the cart_item_price if product price changed.
                $itemPriceAtPurchase = ($product['sale_price'] > 0 && $product['sale_price'] < $product['price']) 
                                             ? (float)$product['sale_price'] 
                                             : (float)$product['price'];
                $itemTotal = $itemPriceAtPurchase * $item['quantity'];
                $orderItemData = [
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $itemPriceAtPurchase,
                    'total' => $itemTotal,
                    // download_link will be populated for actual digital downloads (e.g., game installers)
                    // Product keys will be stored in product_keys table and linked via order_item_id
                    // We will NOT store license_key in download_link here.
                    'download_link' => $product['download_link'] ?? null // Assuming product has a direct download link if not key-based
                ];
                $orderItemId = $this->orderItemModel->createOrderItem($orderItemData);
                if (!$orderItemId) {
                    throw new Exception('Failed to create order item record.');
                }
                // Assign product keys and update stock ONLY if product is key-based (digital)
                if ($product['key_count'] > 0) { // Assuming key_count > 0 implies a digital product needing keys
                    for ($i = 0; $i < $item['quantity']; $i++) {
                        $productKey = $this->productKeyModel->getAvailableKey($item['product_id']);
                        if (!$productKey) {
                            throw new Exception("No available key for product '{$item['title']}'.");
                        }
                        $keyAssigned = $this->productKeyModel->markAsRedeemed($productKey['id'], $orderItemId);
                        if (!$keyAssigned) {
                            throw new Exception("Failed to assign key for product '{$item['title']}'.");
                        }
                    }
                    // Crucial: Decrease product stock (key_count) for digital products
                    $stockUpdated = $this->productModel->updateStock($item['product_id'], -$item['quantity']);
                    if (!$stockUpdated) {
                        throw new Exception("Failed to update stock for product '{$item['title']}'.");
                    }
                } else {
                    // For physical products, update stock_quantity if you have a separate stock field
                    // For now, assuming key_count is the only stock mechanism.
                    // If you have 'stock_quantity' for physical products, update it here:
                    // $this->productModel->updateStockQuantity($item['product_id'], -$item['quantity']);
                }
            }
            // 7. Increment coupon usage count if a coupon was applied
            if ($appliedCoupon) {
                error_log("DEBUG: OrderService::createOrderFromCart - Applied coupon data: " . json_encode($appliedCoupon));

                // First, re-validate the coupon one last time before incrementing
                $freshCoupon = $this->couponModel->findByCode($appliedCoupon['code']);
                IF (!$freshCoupon || !$this->couponModel->isValid($freshCoupon, $subtotal, $userId)) {
                    throw new Exception("Coupon is no longer valid at checkout time.");
                }
                $couponIncremented = $this->couponModel->incrementUsedCount($freshCoupon['id']);
                if (!$couponIncremented) {
                    error_log("ERROR: Failed to increment usage count for coupon ID: " . $freshCoupon['id']);
                    throw new Exception("Failed to increment coupon usage count.");
                } else {
                    error_log("DEBUG: OrderService::createOrderFromCart - Successfully incremented used_count for coupon ID: " . $freshCoupon['id']);
                }
            } else {
                error_log("DEBUG: OrderService::createOrderFromCart - No coupon was applied.");
            }
            // 8. Add initial order status history entry
            $this->orderStatusHistoryModel->addStatusEntry($orderId, 'pending', 'Order created.', $userId);
            // 9. Clear the user's cart
            $this->cartService->clearCart($userId);
            // Commit the transaction
            $this->db->commit();
            return ['status' => 'success', 'message' => 'Order created successfully!', 'order_number' => $orderNumber];
        } catch (Exception $e) {
            // Rollback the transaction on any error
            $this->db->rollBack();
            error_log("Order creation failed for user {$userId}: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to create order: ' . $e->getMessage()];
        }
    }
    /**
     * Retrieves full details for a specific order, ensuring user ownership.
     * @param int $orderId The ID of the order.
     * @param int $userId The ID of the authenticated user.
     * @return array|null Order details if found and owned by user, null otherwise.
     */
    public function getOrderDetails(int $orderId, int $userId): ?array
    {
        $order = $this->orderModel->findById($orderId);
    
        if (!$order || $order['user_id'] !== $userId) {
            return null;
        }
        // Get order items with product details and download_link
        $order['items'] = $this->orderModel->getOrderItems($orderId);
        
        // Get order status history
        $order['history'] = $this->orderStatusHistoryModel->getHistoryForOrder($orderId);
        // Fetch all product keys for each order item if available (for digital products)
        foreach ($order['items'] as &$item) {
            // Use 'product_key_count' as fetched from Order::getOrderItems
            if (isset($item['product_key_count']) && $item['product_key_count'] > 0) { 
                // Use the modified method to get ALL keys for this order_item_id
                $assignedKeys = $this->productKeyModel->findKeysByOrderItemId($item['order_item_id']);
                // Store them as an array
                $item['assigned_keys'] = $assignedKeys; // Changed to 'assigned_keys' (plural)
            } else {
                $item['assigned_keys'] = []; // No keys for non-key-based products
            }
        }
        unset($item); // Unset the reference to the last element
        return $order;
    }
    /**
     * Retrieves a list of all orders for a specific user.
     *
     * @param int $userId The ID of the user.
     * @return array An array of order summaries.
     */
    public function getUserOrders(int $userId): array
    {
        return $this->orderModel->findByUser($userId);
    }
    /**
     * Helper method to generate a unique order number.
     * Can be customized for different formats (e.g., date-based, random string).
     * @return string A unique order number.
     */
    private function generateUniqueOrderNumber(): string
    {
        return 'LUGX-' . date('Ymd-His') . '-' . strtoupper(substr(uniqid(), -6));
    }
    /**
     * Checks how many times a specific user has used a given coupon.
     * This is used to enforce per_user_limit.
     * @param int $userId The ID of the user.
     * @param int $couponId The ID of the coupon.
     * @return int The number of times the user has successfully used this coupon.
     */
    private function getCouponUsageCountForUser(int $userId, int $couponId): int
    {
        try {
            $query = "SELECT COUNT(id) FROM orders WHERE user_id = :user_id AND coupon_code = (SELECT code FROM coupons WHERE id = :coupon_id) AND payment_status = 'paid'";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':coupon_id', $couponId, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting coupon usage for user: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if user has purchased a product
     */
    public function hasUserPurchasedProduct(int $userId, int $productId): bool
    {
        $query = "SELECT COUNT(*) 
                 FROM order_items oi
                 JOIN orders o ON oi.order_id = o.id
                 WHERE o.user_id = :user_id 
                 AND oi.product_id = :product_id
                 AND o.status = 'completed'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
}