<?php
namespace App\Services;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Coupon;
use App\Services\ValidationService;
use PDOException;
use Exception; // Added for general exceptions if needed
class CartService
{
    private Cart $cartModel;
    private Product $productModel;
    private Coupon $couponModel;
    private ValidationService $validationService;
    public function __construct()
    {
        $this->cartModel = new Cart();
        $this->productModel = new Product();
        $this->couponModel = new Coupon();
        $this->validationService = new ValidationService();
        error_log("DEBUG: CartService::__construct - CartService initialized."); // DEBUG
    }
    /**
     * Adds an item to the user's cart or updates its quantity.
     * Includes stock validation.
     *
     * @param int $userId The ID of the user.
     * @param int $productId The ID of the product.
     * @param int $quantity The quantity to add.
     * @return array Result array with 'status' (success/error), 'message', and 'errors' (if validation fails).
     */
    public function addItemToCart(int $userId, int $productId, int $quantity): array
    {
        error_log("DEBUG: CartService::addItemToCart - Called for user {$userId}, product {$productId}, quantity {$quantity}."); // DEBUG
        $this->validationService->resetErrors();
        // 1. Validate input quantity
        if (!$this->validationService->validate(['quantity' => $quantity], ['quantity' => 'required|integer|min:1'])) {
            error_log("DEBUG: CartService::addItemToCart - Quantity validation failed."); // DEBUG
            return ['status' => 'error', 'message' => 'Invalid quantity provided.', 'errors' => $this->validationService->getErrors()];
        }
        // 2. Get product details for stock and price
        error_log("DEBUG: CartService::addItemToCart - Fetching product details for product ID: {$productId}."); // DEBUG
        $product = $this->productModel->getProductForCart($productId);
        if (!$product || $product['stock_status'] === 'out_of_stock' || $product['status'] !== 'published') {
            error_log("DEBUG: CartService::addItemToCart - Product not available or not found. Product: " . json_encode($product)); // DEBUG
            return ['status' => 'error', 'message' => 'Product not available or not found.'];
        }
        error_log("DEBUG: CartService::addItemToCart - Product found: " . json_encode($product)); // DEBUG
        // Determine the price to store in the cart (sale_price if applicable, otherwise regular price)
        $priceToStore = ($product['sale_price'] > 0 && $product['sale_price'] < $product['price'])
                                     ? (float)$product['sale_price']
                                     : (float)$product['price'];
        error_log("DEBUG: CartService::addItemToCart - Price to store: {$priceToStore}."); // DEBUG
        
        // 3. Check current cart quantity for this product
        error_log("DEBUG: CartService::addItemToCart - Checking existing cart item for user {$userId}, product {$productId}."); // DEBUG
        $existingCartItem = $this->cartModel->findByUserAndProduct($userId, $productId);
        $currentCartQuantity = $existingCartItem ? $existingCartItem['quantity'] : 0;
        $requestedTotalQuantity = $currentCartQuantity + $quantity;
        error_log("DEBUG: CartService::addItemToCart - Current cart quantity: {$currentCartQuantity}, Requested total quantity: {$requestedTotalQuantity}."); // DEBUG
        
        // 4. Validate stock (using key_count as available stock)
        if ($product['key_count'] < $requestedTotalQuantity) {
            error_log("DEBUG: CartService::addItemToCart - Not enough stock. Available: {$product['key_count']}, Requested: {$requestedTotalQuantity}."); // DEBUG
            return ['status' => 'error', 'message' => 'Not enough stock available for this product. Max available: ' . $product['key_count'] . '.'];
        }
        try {
            // 5. Add/Update item in cart
            error_log("DEBUG: CartService::addItemToCart - Calling cartModel->addItem for user {$userId}, product {$productId}, quantity {$quantity}, price {$priceToStore}."); // DEBUG
            $success = $this->cartModel->addItem($userId, $productId, $quantity, $priceToStore);
            
            if ($success) {
                error_log("DEBUG: CartService::addItemToCart - Item added/updated successfully in cart model."); // DEBUG
                // Clear any applied coupon if cart contents change significantly
                // This is a safety measure to avoid applying a coupon that might no longer be valid for the new cart
                $this->clearAppliedCouponFromSession();
                return ['status' => 'success', 'message' => 'Product added to cart successfully.'];
            } else {
                error_log("DEBUG: CartService::addItemToCart - cartModel->addItem returned false."); // DEBUG
                return ['status' => 'error', 'message' => 'Failed to add product to cart due to a database issue.'];
            }
        }catch (PDOException $e) {
            error_log("ERROR: CartService::addItemToCart PDOException: " . $e->getMessage()); // DEBUG
            return ['status' => 'error', 'message' => 'An unexpected database error occurred while adding to cart.'];
        }
    }
    /**
     * Updates the quantity of a specific item in the user's cart.
     * Includes stock validation.
     *
     * @param int $userId The ID of the user.
     * @param int $productId The ID of the product in the cart.
     * @param int $newQuantity The new quantity for the item.
     * @return array Result array with 'status' (success/error), 'message', and 'errors' (if validation fails).
     */
    public function updateCartItem(int $userId, int $productId, int $newQuantity): array
    {
        error_log("DEBUG: CartService::updateCartItem - Called for user {$userId}, product {$productId}, new quantity {$newQuantity}."); // DEBUG
        $this->validationService->resetErrors();
        // 1. Validate input quantity
        if (!$this->validationService->validate(['new_quantity' => $newQuantity], ['new_quantity' => 'required|integer|min:0'])) {
            error_log("DEBUG: CartService::updateCartItem - New quantity validation failed."); // DEBUG
            return ['status' => 'error', 'message' => 'Invalid quantity provided.', 'errors' => $this->validationService->getErrors()];
        }
        // 2. Find the cart item
        error_log("DEBUG: CartService::updateCartItem - Finding cart item for user {$userId}, product {$productId}."); // DEBUG
        $cartItem = $this->cartModel->findByUserAndProduct($userId, $productId);
        if (!$cartItem) {
            error_log("DEBUG: CartService::updateCartItem - Cart item not found."); // DEBUG
            return ['status' => 'error', 'message' => 'Item not found in your cart.'];
        }
        error_log("DEBUG: CartService::updateCartItem - Cart item found: " . json_encode($cartItem)); // DEBUG
        // If new quantity is 0, remove the item
        if ($newQuantity === 0) {
            error_log("DEBUG: CartService::updateCartItem - New quantity is 0, calling removeCartItem."); // DEBUG
            return $this->removeCartItem($userId, $productId);
        }
        // 3. Get product details for stock validation
        error_log("DEBUG: CartService::updateCartItem - Fetching product details for product ID: {$productId}."); // DEBUG
        $product = $this->productModel->getProductForCart($productId);
        if (!$product || $product['stock_status'] === 'out_of_stock' || $product['status'] !== 'published') {
            error_log("DEBUG: CartService::updateCartItem - Product not available or not found. Product: " . json_encode($product)); // DEBUG
            return ['status' => 'error', 'message' => 'Product not available or not found.'];
        }
        error_log("DEBUG: CartService::updateCartItem - Product found: " . json_encode($product)); // DEBUG
        // 4. Validate stock
        if ($product['key_count'] < $newQuantity) {
            error_log("DEBUG: CartService::updateCartItem - Not enough stock. Available: {$product['key_count']}, Requested: {$newQuantity}."); // DEBUG
            return ['status' => 'error', 'message' => 'Not enough stock available for the requested quantity. Max available: ' . $product['key_count'] . '.'];
        }
        try {
            // 5. Update item quantity
            error_log("DEBUG: CartService::updateCartItem - Calling cartModel->updateItemQuantity for cart item ID {$cartItem['id']}, new quantity {$newQuantity}."); // DEBUG
            $success = $this->cartModel->updateItemQuantity($cartItem['id'], $newQuantity); // Use 'id' from cart item
            if ($success) {
                error_log("DEBUG: CartService::updateCartItem - Cart item quantity updated successfully in cart model."); // DEBUG
                // Clear any applied coupon if cart contents change significantly
                $this->clearAppliedCouponFromSession();
                return ['status' => 'success', 'message' => 'Cart item quantity updated successfully.'];
            } else {
                error_log("DEBUG: CartService::updateCartItem - cartModel->updateItemQuantity returned false."); // DEBUG
                return ['status' => 'error', 'message' => 'Failed to update cart item quantity due to a database issue.'];
            }
        }catch (PDOException $e) {
            error_log("ERROR: CartService::updateCartItem PDOException: " . $e->getMessage()); // DEBUG
            return ['status' => 'error', 'message' => 'An unexpected database error occurred while updating cart.'];
        }
    }
    /**
     * Removes an item from the user's cart.
     *
     * @param int $userId The ID of the user.
     * @param int $productId The ID of the product to remove.
     * @return array Result array with 'status' (success/error) and 'message'.
     */
    public function removeCartItem(int $userId, int $productId): array
    {
        error_log("DEBUG: CartService::removeCartItem - Called for user {$userId}, product {$productId}."); // DEBUG
        // 1. Find the cart item
        error_log("DEBUG: CartService::removeCartItem - Finding cart item for user {$userId}, product {$productId}."); // DEBUG
        $cartItem = $this->cartModel->findByUserAndProduct($userId, $productId);
        if (!$cartItem) {
            error_log("DEBUG: CartService::removeCartItem - Cart item not found."); // DEBUG
            return ['status' => 'error', 'message' => 'Cart item not found in your cart.'];
        }
        error_log("DEBUG: CartService::removeCartItem - Cart item found: " . json_encode($cartItem)); // DEBUG
        try {
            // 2. Remove the item
            error_log("DEBUG: CartService::removeCartItem - Calling cartModel->removeItem for cart item ID {$cartItem['id']}."); // DEBUG
            $success = $this->cartModel->removeItem($cartItem['id']); // Use 'id' from cart item
            if ($success) {
                error_log("DEBUG: CartService::removeCartItem - Item removed successfully from cart model."); // DEBUG
                // If the cart becomes empty, clear any applied coupon from the session
                if (empty($this->getCart($userId))) {
                    $this->clearAppliedCouponFromSession();
                }
                return ['status' => 'success', 'message' => 'Product removed from cart successfully.'];
            } else {
                error_log("DEBUG: CartService::removeCartItem - cartModel->removeItem returned false."); // DEBUG
                return ['status' => 'error', 'message' => 'Failed to remove product from cart due to a database issue.'];
            }
        }catch (PDOException $e) {
            error_log("ERROR: CartService::removeCartItem PDOException: " . $e->getMessage()); // DEBUG
            return ['status' => 'error', 'message' => 'An unexpected database error occurred while removing from cart.'];
        }
    }
    /**
     * Gets the current contents of the user's cart with product details.
     *
     * @param int $userId The ID of the user.
     * @return array An array of cart items.
     */
    public function getCart(int $userId): array
    {
        error_log("DEBUG: CartService::getCart - Called for user {$userId}."); // DEBUG
        try {
            error_log("DEBUG: CartService::getCart - Calling cartModel->getCartContents for user {$userId}."); // DEBUG
            $cartItems  = $this->cartModel->getCartContents($userId);
            error_log("DEBUG: CartService::getCart - Received raw cart items from model: " . json_encode($cartItems)); // DEBUG
            foreach ($cartItems as &$item) {
                $item['cart_item_price'] = (float)$item['cart_item_price'];
                $item['current_product_price'] = (float)$item['current_product_price'];
                $item['sale_price'] = (float)$item['sale_price'];
                $item['quantity'] = (int)$item['quantity'];
                $item['available_stock_keys'] = (int)$item['available_stock_keys'];
                // Add subtotal for each item for easier frontend rendering
                $item['subtotal'] = $item['cart_item_price'] * $item['quantity'];
            }
            unset($item); // Break the reference with the last element
            error_log("DEBUG: CartService::getCart - Returning processed cart items: " . json_encode($cartItems)); // DEBUG
            return $cartItems;
        }catch (PDOException $e) {
            error_log("ERROR: CartService::getCart PDOException: " . $e->getMessage()); // DEBUG
            return [];
        }
    }
    /**
     * Calculates the total amounts for the cart, including subtotal, discount, and final total.
     *
     * @param int $userId The ID of the user.
     * @param string|null $couponCode Optional coupon code to apply.
     * If null, it will check the session for a previously applied coupon.
     * @return array An associative array with 'subtotal', 'discount', 'total', 'item_count', 'applied_coupon' (if any).
     */
    public function calculateCartTotals(int $userId, ?string $couponCode = null): array
    {
        error_log("DEBUG: CartService::calculateCartTotals - Called for user {$userId}, coupon: " . ($couponCode ?? 'null') . "."); // DEBUG
        $cartItems = $this->getCart($userId);
        $subtotal = 0.0;
        $discount = 0.0;
        $itemCount = 0;
        $appliedCoupon = null;
        $couponMessage = ''; // To provide specific feedback on coupon validity
        foreach ($cartItems as $item) {
            $itemPrice = $item['cart_item_price'];
            $subtotal += $itemPrice * $item['quantity'];
            $itemCount += $item['quantity'];
        }
        error_log("DEBUG: CartService::calculateCartTotals - Subtotal: {$subtotal}, Item Count: {$itemCount}."); // DEBUG
        // Determine which coupon code to use: explicit parameter or session
        $effectiveCouponCode = $couponCode;
        if (empty($effectiveCouponCode) && isset($_SESSION['applied_coupon_code'])) {
            $effectiveCouponCode = $_SESSION['applied_coupon_code'];
            error_log("DEBUG: CartService::calculateCartTotals - Using coupon from session: " . $effectiveCouponCode);
        } else if (!empty($effectiveCouponCode)) {
            error_log("DEBUG: CartService::calculateCartTotals - Using coupon from parameter: " . $effectiveCouponCode);
        } else {
            error_log("DEBUG: CartService::calculateCartTotals - No coupon code to apply (neither parameter nor session).");
        }
        if (!empty($effectiveCouponCode)) {
            $coupon = $this->couponModel->findByCode($effectiveCouponCode);
            if ($coupon) {
                error_log("DEBUG: CartService::calculateCartTotals - Coupon found: " . json_encode($coupon)); // DEBUG
                // Validate coupon using Coupon model's isValid method
                if ($this->couponModel->isValid($coupon, $subtotal, $userId)) {
                    $discount = $this->couponModel->calculateDiscount($coupon, $subtotal);
                    $appliedCoupon = $coupon; // Store applied coupon data
                    error_log("DEBUG: CartService::calculateCartTotals - Coupon applied. Discount: {$discount}. Coupon data: " . json_encode($coupon));
                    $couponMessage = 'Coupon applied successfully!';
                } else {
                    // Get the specific reason for invalidity from CouponModel
                    $couponMessage = $this->couponModel->getInvalidReason();
                    error_log("DEBUG: CartService::calculateCartTotals - Coupon '{$effectiveCouponCode}' invalid: {$couponMessage}");
                    // If coupon is invalid, ensure it's removed from session if it was from session
                    if ($effectiveCouponCode === ($_SESSION['applied_coupon_code'] ?? null)) {
                        $this->clearAppliedCouponFromSession();
                        $couponMessage .= ' (Cleared from session)';
                    }
                }
            } else {
                $couponMessage = 'Invalid coupon code.';
                error_log("DEBUG: CartService::calculateCartTotals - Coupon '{$effectiveCouponCode}' not found."); // DEBUG
                // If coupon not found, ensure it's removed from session if it was from session
                if ($effectiveCouponCode === ($_SESSION['applied_coupon_code'] ?? null)) {
                    $this->clearAppliedCouponFromSession();
                    $couponMessage .= ' (Cleared from session)';
                }
            }
        }
        $total = $subtotal - $discount;
        if ($total < 0) { // Ensure total doesn't go negative
            $total = 0;
        }
        error_log("DEBUG: CartService::calculateCartTotals - Final Total: {$total}."); // DEBUG
        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
            'item_count' => $itemCount,
            'applied_coupon' => $appliedCoupon,
            'coupon_message' => $couponMessage // Added for specific frontend feedback
        ];
    }
    /**
     * Applies a coupon to the user's cart and recalculates totals.
     *
     * @param int $userId The ID of the user.
     * @param string $couponCode The code of the coupon to apply.
     * @return array Result array with 'status' (success/error), 'message', and 'totals' (if successful).
     */
    public function applyCouponToCart(int $userId, string $couponCode): array
    {
        error_log("DEBUG: CartService::applyCouponToCart - Called for user {$userId}, coupon: {$couponCode}."); // DEBUG
        $this->validationService->resetErrors();
        // 1. Validate coupon code input
        if (!$this->validationService->validate(['coupon_code' => $couponCode], ['coupon_code' => 'required|string|min:3|max:50'])) {
            error_log("DEBUG: CartService::applyCouponToCart - Coupon code validation failed."); // DEBUG
            $this->clearAppliedCouponFromSession(); // Clear any existing coupon if new input is invalid
            return ['status' => 'error', 'message' => 'Invalid coupon code format.', 'errors' => $this->validationService->getErrors()];
        }
        // 2. Find the coupon
        error_log("DEBUG: CartService::applyCouponToCart - Finding coupon by code: {$couponCode}."); // DEBUG
        $coupon = $this->couponModel->findByCode($couponCode);
        if (!$coupon) {
            error_log("DEBUG: CartService::applyCouponToCart - Coupon not found."); // DEBUG
            $this->clearAppliedCouponFromSession(); // Clear if coupon not found
            return ['status' => 'error', 'message' => 'Coupon not found.'];
        }
        error_log("DEBUG: CartService::applyCouponToCart - Coupon found: " . json_encode($coupon)); // DEBUG
        // 3. Calculate current cart subtotal to validate coupon against
        error_log("DEBUG: CartService::applyCouponToCart - Getting cart items for subtotal calculation."); // DEBUG
        $cartItems = $this->getCart($userId);
        $subtotal = 0.0;
        foreach ($cartItems as $item) {
            $itemPrice = $item['cart_item_price'];
            $subtotal += $itemPrice * $item['quantity'];
        }
        error_log("DEBUG: CartService::applyCouponToCart - Calculated subtotal for validation: {$subtotal}."); // DEBUG
        // 4. Validate coupon using the Coupon model's logic
        if (!$this->couponModel->isValid($coupon, $subtotal, $userId)) {
            $reason = $this->couponModel->getInvalidReason();
            error_log("DEBUG: CartService::applyCouponToCart - Coupon is not valid or conditions not met. Reason: " . $reason); // DEBUG
            $this->clearAppliedCouponFromSession(); // Clear if coupon is not valid
            return ['status' => 'error', 'message' => $reason ?: 'Coupon is not valid or conditions not met.'];
        }
        error_log("DEBUG: CartService::applyCouponToCart - Coupon is valid."); // DEBUG
        // 5. Calculate totals with applied coupon
        // Pass the coupon code to calculateCartTotals so it uses this specific coupon
        $totals = $this->calculateCartTotals($userId, $couponCode);
        error_log("DEBUG: CartService::applyCouponToCart - Recalculated totals with coupon: " . json_encode($totals)); // DEBUG
        // If totals show no discount, it means the coupon didn't apply (e.g., min amount not met, but isValid passed)
        if ($totals['discount'] <= 0) {
            error_log("DEBUG: CartService::applyCouponToCart - Coupon applied but no discount resulted. Clearing session coupon."); // DEBUG
            $this->clearAppliedCouponFromSession();
            return ['status' => 'error', 'message' => $totals['coupon_message'] ?: 'Coupon could not be applied. Please check conditions.'];
        }
        // --- IMPORTANT: Store the applied coupon in the session for persistence ---
        $_SESSION['applied_coupon_code'] = $couponCode;
        $_SESSION['applied_coupon_id'] = $coupon['id']; // Store ID for easier lookup
        error_log("DEBUG: CartService::applyCouponToCart - Coupon '{$couponCode}' (ID: {$coupon['id']}) successfully applied and stored in session."); // DEBUG
        error_log("DEBUG: CartService::applyCouponToCart - Coupon applied successfully. Final result: " . json_encode(['status' => 'success', 'message' => 'Coupon applied successfully!', 'totals' => $totals])); // DEBUG
        return ['status' => 'success', 'message' => 'Coupon applied successfully!', 'totals' => $totals];
    }
    /**
     * Clears all items from a user's cart.
     * This method is added to CartService to be called by OrderService.
     *
     * @param int $userId The ID of the user.
     * @return bool True on success, false on failure.
     */
    public function clearCart(int $userId): bool
    {
        error_log("DEBUG: CartService::clearCart - Called for user {$userId}."); // DEBUG
        try {
            $success = $this->cartModel->clearCart($userId);
            if ($success) {
                $this->clearAppliedCouponFromSession(); // Clear coupon if cart is emptied
                error_log("DEBUG: CartService::clearCart - Cart cleared and coupon removed from session."); // DEBUG
            } else {
                error_log("DEBUG: CartService::clearCart - cartModel->clearCart returned false."); // DEBUG
            }
            return $success;
        } catch (PDOException $e) {
            error_log("ERROR: CartService::clearCart PDOException: " . $e->getMessage()); // DEBUG
            return false;
        }
    }
    /**
     * Removes the currently applied coupon from the user's cart session.
     * This method is called by CartController to explicitly remove the coupon.
     * @return void
     */
    public function removeCouponFromCart(): void // Renamed from removeCoupon to be more specific
    {
        $this->clearAppliedCouponFromSession();
        error_log("DEBUG: CartService::removeCouponFromCart - Explicitly removed coupon from session.");
    }
    /**
     * Helper to clear applied coupon from session.
     */
    private function clearAppliedCouponFromSession(): void
    {
        if (isset($_SESSION['applied_coupon_code'])) {
            unset($_SESSION['applied_coupon_code']);
        }
        if (isset($_SESSION['applied_coupon_id'])) {
            unset($_SESSION['applied_coupon_id']);
        }
        error_log("DEBUG: CartService::clearAppliedCouponFromSession - Applied coupon cleared from session.");
    }
}