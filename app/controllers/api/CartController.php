<?php
namespace App\Controllers\Api;
use App\Core\Controller;
use App\Services\CartService;
use App\Services\ValidationService;
use App\Services\AuthService; // Make sure AuthService is imported

class CartController extends Controller
{
    private CartService $cartService;
    private ValidationService $validationService;
    private AuthService $authService; // Declare AuthService

    public function __construct()
    {
        parent::__construct();
        error_log("DEBUG: CartController::__construct - Called."); // ADDED DEBUG LOG
        // Try-catch block for instantiation to catch errors during object creation
        try {
            $this->cartService = new CartService();
            error_log("DEBUG: CartController::__construct - CartService instantiated."); // ADDED DEBUG LOG
        } catch (\Throwable $th) { // Catching Throwable to capture all errors/exceptions
            error_log("CRITICAL ERROR: CartController::__construct - Failed to instantiate CartService: " . $th->getMessage() . " in " . $th->getFile() . " on line " . $th->getLine());
            // It's crucial to exit here if a core service cannot be instantiated
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Internal server error during cart service initialization.']);
            exit;
        }

        try {
            $this->validationService = new ValidationService();
            error_log("DEBUG: CartController::__construct - ValidationService instantiated."); // ADDED DEBUG LOG
        } catch (\Throwable $th) {
            error_log("CRITICAL ERROR: CartController::__construct - Failed to instantiate ValidationService: " . $th->getMessage() . " in " . $th->getFile() . " on line " . $th->getLine());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Internal server error during validation service initialization.']);
            exit;
        }

        // Initialize AuthService
        try {
            $this->authService = new AuthService();
            error_log("DEBUG: CartController::__construct - AuthService instantiated.");
        } catch (\Throwable $th) {
            error_log("CRITICAL ERROR: CartController::__construct - Failed to instantiate AuthService: " . $th->getMessage() . " in " . $th->getFile() . " on line " . $th->getLine());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Internal server error during auth service initialization.']);
            exit;
        }
    }

    /**
     * Helper method to get JSON request body.
     * Mimics Request::getJsonBody() functionality based on common PHP practices.
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
     * Assumes user ID is stored in $_SESSION['user_id'] after successful login.
     * This method should be robust and ideally rely on a dedicated Auth service.
     * @return int The authenticated user ID.
     * @throws \Exception If user is not authenticated (should be caught by AuthMiddleware).
     */
    private function getAuthenticatedUserId(): int
    {
        // Use the existing getAuthenticatedUserId from AuthService
        $userId = $this->authService->getAuthenticatedUserId();
        if ($userId === null) {
            throw new \Exception("User not authenticated.");
        }
        return $userId;
    }

    /**
     * Add an item to the cart.
     * POST /api/cart/add
     * Request Body: { "product_id": int, "quantity": int }
     */
    public function addItem(): void
    {
        error_log("DEBUG: CartController::addItem - Request received."); // DEBUG
        $data = $this->getJsonBody();

        if (empty($data)){
            error_log("DEBUG: CartController::addItem - Empty or invalid request body."); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Invalid or empty request body.'], 400);
            return;
        }

        try {
            $userId = $this->getAuthenticatedUserId();
        }catch (\Exception $e) {
            error_log("DEBUG: CartController::addItem - Authentication failed: " . $e->getMessage()); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized. Please log in.'], 401);
            return;
        }

        // Validate incoming data
        if (!$this->validationService->validate($data, [
            'product_id' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1'
        ])) {
            error_log("DEBUG: CartController::addItem - Validation failed: " . json_encode($this->validationService->getErrors())); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $this->validationService->getErrors()], 400);
            return;
        }

        $productId = (int)$data['product_id'];
        $quantity = (int)$data['quantity'];

        error_log("DEBUG: CartController::addItem - Calling cartService->addItemToCart for user {$userId}, product {$productId}, quantity {$quantity}."); // DEBUG
        $result = $this->cartService->addItemToCart($userId, $productId, $quantity);

        if ($result['status'] === 'success') {
            error_log("DEBUG: CartController::addItem - Item added successfully. Result: " . json_encode($result)); // DEBUG
            $this->renderApiJson($result, 200);
        } else {
            $statusCode = 400; // Bad Request for most service errors
            if (str_contains($result['message'], 'not found') || str_contains($result['message'], 'not available')) {
                $statusCode = 404; // Not Found for product issues
            } elseif (str_contains($result['message'], 'Not enough stock')) {
                $statusCode = 409; // Conflict for stock issues
            }
            error_log("DEBUG: CartController::addItem - Failed to add item. Result: " . json_encode($result) . " Status: " . $statusCode); // DEBUG
            $this->renderApiJson($result, $statusCode);
        }
    }

    /**
     * Update the quantity of an item in the cart.
     * PUT /api/cart/update
     * Request Body: { "product_id": int, "new_quantity": int }
     */
    public function updateItem(): void
    {
        error_log("DEBUG: CartController::updateItem - Request received."); // DEBUG
        $data = $this->getJsonBody();

        if (empty($data)) {
            error_log("DEBUG: CartController::updateItem - Empty or invalid request body."); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Invalid or empty request body.'], 400);
            return;
        }

        try {
            $userId = $this->getAuthenticatedUserId();
        } catch (\Exception $e) {
            error_log("DEBUG: CartController::updateItem - Authentication failed: " . $e->getMessage()); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized. Please log in.'], 401);
            return;
        }

        if (!$this->validationService->validate($data, [
            'product_id' => 'required|integer|min:1',
            'new_quantity' => 'required|integer|min:0' // Allow 0 to remove item
        ])) {
            error_log("DEBUG: CartController::updateItem - Validation failed: " . json_encode($this->validationService->getErrors())); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $this->validationService->getErrors()], 400);
            return;
        }

        $productId = (int)$data['product_id'];
        $newQuantity = (int)$data['new_quantity'];

        error_log("DEBUG: CartController::updateItem - Calling cartService->updateCartItem for user {$userId}, product {$productId}, new quantity {$newQuantity}."); // DEBUG
        $result = $this->cartService->updateCartItem($userId, $productId, $newQuantity);

        if ($result['status'] === 'success') {
            error_log("DEBUG: CartController::updateItem - Item updated successfully. Result: " . json_encode($result)); // DEBUG
            $this->renderApiJson($result, 200);
        } else {
            $statusCode = 400;
            if (str_contains($result['message'], 'not found')) {
                $statusCode = 404;
            } elseif (str_contains($result['message'], 'Not enough stock')) {
                $statusCode = 409;
            }
            error_log("DEBUG: CartController::updateItem - Failed to update item. Result: " . json_encode($result) . " Status: " . $statusCode); // DEBUG
            $this->renderApiJson($result, $statusCode);
        }
    }

    /**
     * Remove an item from the cart.
     * DELETE /api/cart/remove
     * Request Body: { "product_id": int }
     */
    public function removeItem(): void
    {
        error_log("DEBUG: CartController::removeItem - Request received."); // DEBUG
        $data = $this->getJsonBody();

        if (empty($data)) {
            error_log("DEBUG: CartController::removeItem - Empty or invalid request body."); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Invalid or empty request body.'], 400);
            return;
        }

        try {
            $userId = $this->getAuthenticatedUserId();
        } catch (\Exception $e) {
            error_log("DEBUG: CartController::removeItem - Authentication failed: " . $e->getMessage()); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized. Please log in.'], 401);
            return;
        }

        if (!$this->validationService->validate($data, [
            'product_id' => 'required|integer|min:1'
        ])) {
            error_log("DEBUG: CartController::removeItem - Validation failed: " . json_encode($this->validationService->getErrors())); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $this->validationService->getErrors()], 400);
            return;
        }

        $productId = (int)$data['product_id'];

        error_log("DEBUG: CartController::removeItem - Calling cartService->removeCartItem for user {$userId}, product {$productId}."); // DEBUG
        $result = $this->cartService->removeCartItem($userId, $productId);

        if ($result['status'] === 'success') {
            error_log("DEBUG: CartController::removeItem - Item removed successfully. Result: " . json_encode($result)); // DEBUG
            $this->renderApiJson($result, 200);
        } else {
            $statusCode = 400;
            if (str_contains($result['message'], 'not found')) {
                $statusCode = 404;
            }
            error_log("DEBUG: CartController::removeItem - Failed to remove item. Result: " . json_encode($result) . " Status: " . $statusCode); // DEBUG
            $this->renderApiJson($result, $statusCode);
        }
    }

    /**
     * Get the current contents of the user's cart and calculated totals.
     * GET /api/cart
     */
    public function getCart(): void
    {
        error_log("DEBUG: CartController::getCart - Request received."); // DEBUG
        try {
            $userId = $this->getAuthenticatedUserId();
            error_log("DEBUG: CartController::getCart - User authenticated, ID: {$userId}."); // DEBUG
        } catch (\Exception $e) {
            error_log("DEBUG: CartController::getCart - Authentication failed: " . $e->getMessage()); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized. Please log in.'], 401);
            return;
        }

        error_log("DEBUG: CartController::getCart - Calling cartService->getCart for user {$userId}."); // DEBUG
        // Wrap service calls in try-catch to log any internal service errors
        try {
            $cartItems = $this->cartService->getCart($userId);
            error_log("DEBUG: CartController::getCart - Received raw cart items from service: " . json_encode($cartItems, JSON_THROW_ON_ERROR)); // DEBUG
        } catch (\Throwable $th) {
            error_log("CRITICAL ERROR: CartController::getCart - Failed to get cart items from CartService: " . $th->getMessage() . " in " . $th->getFile() . " on line " . $th->getLine());
            $this->renderApiJson(['status' => 'error', 'message' => 'Internal server error while fetching cart items.'], 500);
            return;
        }

        error_log("DEBUG: CartController::getCart - Calling cartService->calculateCartTotals for user {$userId}."); // DEBUG
        try {
            $totals = $this->cartService->calculateCartTotals($userId); // Calculate totals without coupon initially
            error_log("DEBUG: CartController::getCart - Received cart totals from service: " . json_encode($totals, JSON_THROW_ON_ERROR)); // DEBUG
        } catch (\Throwable $th) {
            error_log("CRITICAL ERROR: CartController::getCart - Failed to calculate cart totals from CartService: " . $th->getMessage() . " in " . $th->getFile() . " on line " . $th->getLine());
            $this->renderApiJson(['status' => 'error', 'message' => 'Internal server error while calculating cart totals.'], 500);
            return;
        }

        // ADDED: Log the data before rendering JSON (already existed, but keeping it)
        error_log("DEBUG: CartController::getCart - Items: " . json_encode($cartItems, JSON_THROW_ON_ERROR) . ", Totals: " . json_encode($totals, JSON_THROW_ON_ERROR));

        error_log("DEBUG: CartController::getCart - About to render JSON. Final data: " . json_encode([ // DEBUG
            'status' => 'success',
            'message' => 'Cart contents retrieved successfully.',
            'data' => [
                'items' => $cartItems,
                'totals' => $totals
            ]
        ], JSON_THROW_ON_ERROR));

        $this->renderApiJson([
            'status' => 'success',
            'message' => 'Cart contents retrieved successfully.',
            'data' => [
                'items' => $cartItems,
                'totals' => $totals
            ]
        ], 200);
    }

    /**
     * Apply a coupon code to the cart.
     * POST /api/cart/apply-coupon
     * Request Body: { "coupon_code": string }
     */
    public function applyCoupon(): void
    {
        error_log("DEBUG: CartController::applyCoupon - Request received."); // DEBUG
        $data = $this->getJsonBody();

        if (empty($data)) {
            error_log("DEBUG: CartController::applyCoupon - Empty or invalid request body."); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Invalid or empty request body.'], 400);
            return;
        }
        try {
            $userId = $this->getAuthenticatedUserId();
        } catch (\Exception $e) {
            error_log("DEBUG: CartController::applyCoupon - Authentication failed: " . $e->getMessage()); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized. Please log in.'], 401);
            return;
        }
        if (!$this->validationService->validate($data, [
            'coupon_code' => 'required|string|min:3|max:50'
        ])) {
            error_log("DEBUG: CartController::applyCoupon - Validation failed: " . json_encode($this->validationService->getErrors())); // DEBUG
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $this->validationService->getErrors()], 400);
            return;
        }

        $couponCode = $data['coupon_code'];

        error_log("DEBUG: CartController::applyCoupon - Calling cartService->applyCouponToCart for user {$userId}, coupon {$couponCode}."); // DEBUG
        $result = $this->cartService->applyCouponToCart($userId, $couponCode);

        if ($result['status'] === 'success') {
            error_log("DEBUG: CartController::applyCoupon - Coupon applied successfully. Result: " . json_encode($result)); // DEBUG
            $this->renderApiJson($result, 200);
        } else {
            $statusCode = 400; // Bad Request for invalid coupon, etc.
            if (str_contains($result['message'], 'not found')) {
                $statusCode = 404;
            }
            error_log("DEBUG: CartController::applyCoupon - Failed to apply coupon. Result: " . json_encode($result) . " Status: " . $statusCode); // DEBUG
            $this->renderApiJson($result, $statusCode);
        }
    }

    /**
     * Remove the applied coupon from the cart.
     * DELETE /api/cart/remove-coupon
     */
    public function removeCoupon(): void
    {
        error_log("DEBUG: CartController::removeCoupon - Request received.");
        try {
            $userId = $this->getAuthenticatedUserId();
            error_log("DEBUG: CartController::removeCoupon - User authenticated, ID: {$userId}.");
        } catch (\Exception $e) {
            error_log("DEBUG: CartController::removeCoupon - Authentication failed: " . $e->getMessage());
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized. Please log in.'], 401);
            return;
        }

        try {
            $this->cartService->removeCouponFromCart(); // Call service to remove coupon
            // After removing coupon, recalculate totals to send updated cart data
            $cartItems = $this->cartService->getCart($userId);
            $totals = $this->cartService->calculateCartTotals($userId); // Recalculate without passing couponCode

            error_log("DEBUG: CartController::removeCoupon - Coupon removed successfully. Cart data: " . json_encode($cartItems, JSON_THROW_ON_ERROR) . ", Totals: " . json_encode($totals, JSON_THROW_ON_ERROR));
            $this->renderApiJson([
                'status' => 'success',
                'message' => 'Coupon removed successfully!',
                'data' => [
                    'items' => $cartItems,
                    'totals' => $totals
                ]
            ], 200);
        } catch (\Throwable $th) {
            error_log("CRITICAL ERROR: CartController::removeCoupon - Failed to remove coupon: " . $th->getMessage() . " in " . $th->getFile() . " on line " . $th->getLine());
            $this->renderApiJson(['status' => 'error', 'message' => 'Internal server error while removing coupon.'], 500);
            return;
        }
    }
}
