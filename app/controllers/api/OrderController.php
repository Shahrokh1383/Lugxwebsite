<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Services\OrderService;
use App\Services\ValidationService;

class OrderController extends Controller
{
    private OrderService $orderService;
    private ValidationService $validationService;

    public function __construct()
    {
        parent::__construct();
        $this->orderService = new OrderService();
        $this->validationService = new ValidationService();
    }

    /**
     * Helper method to get JSON request body.
     * Replicated from CartController for consistency.
     * @return array Decoded JSON data, or empty array if invalid/not found.
     */
    private function getJsonBody(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Helper method to get the authenticated user ID.
     * Replicated from CartController for consistency.
     * @return int The authenticated user ID.
     * @throws \Exception If user is not authenticated (should be caught by AuthMiddleware).
     */
    private function getAuthenticatedUserId(): int
    {
        if (isset($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }
        throw new \Exception("User not authenticated.");
    }

    /**
     * Create a new order from the user's cart.
     * POST /api/orders
     * Request Body: {
     * "payment_method": "credit_card" | "paypal" | "bank_transfer" | "crypto",
     * "coupon_code": "YOUR_COUPON" | null,
     * "billing_address": { ... }, // JSON object for billing address
     * "shipping_address": { ... }, // JSON object for shipping address
     * "notes": "Optional notes" | null
     * }
     */
    public function createOrder(): void
    {
        $data = $this->getJsonBody();

        if (empty($data)) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Invalid or empty request body.'], 400);
            return;
        }

        try {
            $userId = $this->getAuthenticatedUserId();
        } catch (\Exception $e) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized. Please log in.'], 401);
            return;
        }

        // Define validation rules for nested address fields using dot notation
        $rules = [
            'payment_method' => 'required|string|in:credit_card,paypal,bank_transfer,crypto',
            'coupon_code' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',

            // Shipping Address Fields
            'shipping_address.first_name' => 'required|string|max:100',
            'shipping_address.last_name' => 'required|string|max:100',
            'shipping_address.address' => 'required|string|max:255', // Corresponds to address_line1 in JS
            'shipping_address.address2' => 'nullable|string|max:255',
            'shipping_address.city' => 'required|string|max:100',
            'shipping_address.state' => 'required|string|max:100',
            'shipping_address.postal_code' => 'required|string|max:20', // Corresponds to zip_code in JS
            'shipping_address.country' => 'required|string|max:100',
            'shipping_address.phone' => 'nullable|string|max:20',

            // Billing Address Fields
            'billing_address.first_name' => 'required|string|max:100',
            'billing_address.last_name' => 'required|string|max:100',
            'billing_address.address' => 'required|string|max:255', // Corresponds to address_line1 in JS
            'billing_address.address2' => 'nullable|string|max:255',
            'billing_address.city' => 'required|string|max:100',
            'billing_address.state' => 'required|string|max:100',
            'billing_address.postal_code' => 'required|string|max:20', // Corresponds to zip_code in JS
            'billing_address.country' => 'required|string|max:100',
            'billing_address.phone' => 'nullable|string|max:20',
        ];

        if (!$this->validationService->validate($data, $rules)) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $this->validationService->getErrors()], 400);
            return;
        }

        if (!isset($data['billing_address']) || !is_array($data['billing_address']) || empty($data['billing_address'])) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Billing address is required and must be a valid object.'], 400);
            return;
        }
        if (!isset($data['shipping_address']) || !is_array($data['shipping_address']) || empty($data['shipping_address'])) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Shipping address is required and must be a valid object.'], 400);
            return;
        }

        // Pass the entire $data array to the service, it will extract addresses
        $result = $this->orderService->createOrderFromCart($userId, $data);
        
        if ($result['status'] === 'success') {
            $this->renderApiJson($result, 201); // 201 Created
        } else {
            $statusCode = 400; // Default to Bad Request
            if (str_contains($result['message'], 'Your cart is empty')) {
                $statusCode = 400; // Still bad request
            } elseif (str_contains($result['message'], 'Not enough stock') || str_contains($result['message'], 'No available key')) {
                $statusCode = 409; // Conflict
            } elseif (str_contains($result['message'], 'exceeded the usage limit')) {
                $statusCode = 403; // Forbidden
            }
            $this->renderApiJson($result, $statusCode);
        }
    }

    /**
     * Get a list of all orders for the authenticated user.
     * GET /api/orders
     */
    public function getUserOrders(): void
    {
        try {
            $userId = $this->getAuthenticatedUserId();
        } catch (\Exception $e) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized. Please log in.'], 401);
            return;
        }

        $orders = $this->orderService->getUserOrders($userId);

        $this->renderApiJson([
            'status' => 'success',
            'message' => 'User orders retrieved successfully.',
            'data' => $orders
        ], 200);
    }

    /**
     * Get full details of a specific order for the authenticated user.
     * GET /api/orders/{id}
     * @param string $orderId The ID of the order from the URL.
     */
    public function getOrderDetails(string $orderId): void
    {
        // Validate orderId is an integer
        if (!filter_var($orderId, FILTER_VALIDATE_INT)) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Invalid order ID format.'], 400);
            return;
        }

        $orderId = (int)$orderId;

        try {
            $userId = $this->getAuthenticatedUserId();
        } catch (\Exception $e) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized. Please log in.'], 401);
            return;
        }

        $order = $this->orderService->getOrderDetails($orderId, $userId);

        if ($order) {
            $this->renderApiJson(['status' => 'success', 'message' => 'Order details retrieved successfully.', 'data' => $order], 200);
        } else {
            $this->renderApiJson(['status' => 'error', 'message' => 'Order not found or you do not have permission to view it.'], 404);
        }
    }
}
