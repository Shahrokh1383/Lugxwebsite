<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\AuthService;
use App\Services\ValidationService;
use App\Services\SecurityService;

class UserController extends Controller
{
    private User $userModel;
    private UserAddress $userAddressModel;
    private AuthService $authService;
    private ValidationService $validationService;
    private SecurityService $securityService;

    public function __construct()
    {
        $this->userModel = new User();
        $this->userAddressModel = new UserAddress();
        $this->authService = new AuthService();
        $this->validationService = new ValidationService();
        $this->securityService = new SecurityService();
    }

    /**
     * Get the authenticated user's profile information.
     * GET /api/user/profile
     */
    public function getProfile()
    {
        $userId = $this->authService->getAuthenticatedUserId();

        if (!$userId){
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        $user = $this->userModel->findById($userId);

        if (!$user) {
            $this->renderApiJson(['status' => 'error', 'message' => 'User not found'], 404);
            return;
        }

        // Remove sensitive data before sending
        unset($user['password_hash']);
        unset($user['password_reset_token']);
        unset($user['password_reset_expiry']);
        unset($user['verification_token']);

        $this->renderApiJson(['status' => 'success', 'user' => $user], 200);
    }

    /**
     * Update the authenticated user's profile information.
     * PUT /api/user/profile
     */
    public function updateProfile()
    {
        $userId = $this->authService->getAuthenticatedUserId();

        if (!$userId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Define validation rules for profile update
        $rules = [
            'first_name' => 'required|string|min:2|max:50',
            'last_name' => 'required|string|min:2|max:50',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            // 'avatar' => 'nullable|string|max:255'
        ];

        $this->validationService->validate($input , $rules);

        if (!$this->validationService->passes()) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed', 'errors' => $this->validationService->getErrors()], 422);
            return;
        }

        // Sanitize input data
        $sanitizedData = [
            'first_name' => $this->securityService->sanitizeInput($input['first_name']),
            'last_name' => $this->securityService->sanitizeInput($input['last_name']),
            'phone' => $this->securityService->sanitizeInput($input['phone'] ?? null),
            'date_of_birth' => $input['date_of_birth'] ?? null,
            'gender' => $input['gender'] ?? null,
            // 'avatar' => $input['avatar'] ?? null,
        ];

        // Update user profile in the database
        if ($this->userModel->updateProfile($userId, $sanitizedData)) {
            $this->renderApiJson(['status' => 'success', 'message' => 'Profile updated successfully'], 200);
        }else {
            $this->renderApiJson(['status' => 'error', 'message' => 'Failed to update profile'], 500);
        }
    }
        /**
     * Change the authenticated user's password.
     * PUT /api/user/change-password
     */
    public function changePassword()
    {
        $userId = $this->authService->getAuthenticatedUserId();

        if (!$userId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Define validation rules for password change
        $rules = [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed', // 'confirmed' checks for new_password_confirmation
            'new_password_confirmation' => 'required|string',
        ];

        $this->validationService->validate($input, $rules);

        if (!$this->validationService->passes()) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed', 'errors' => $this->validationService->getErrors()], 422);
            return;
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            $this->renderApiJson(['status' => 'error', 'message' => 'User not found'], 404);
            return;
        }

        error_log("DEBUG: Change Password - User ID: " . $userId);
        error_log("DEBUG: Change Password - Current password from input: " . $input['current_password']);
        error_log("DEBUG: Change Password - Stored password hash from DB: " . $user['password_hash']);

        // Verify current password
        if (!$this->securityService->verifyPassword($input['current_password'], $user['password_hash'])) {
            error_log("DEBUG: Change Password - Current password verification FAILED.");
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed', 'errors' => ['current_password' => 'Current password is incorrect.']], 422);
            return;
        }
        error_log("DEBUG: Change Password - Current password verification SUCCESS.");

        // Hash the new password
        $newPasswordHash = $this->securityService->hashPassword($input['new_password']);
        error_log("DEBUG: Change Password - New password from input: " . $input['new_password']);
        error_log("DEBUG: Change Password - New hashed password: " . $newPasswordHash);


        // Update password in the database
        if ($this->userModel->changePassword($userId, $newPasswordHash)) {
            error_log("DEBUG: Change Password - Password updated successfully in DB for user ID: " . $userId);
            // After successful password change, it's good practice to re-authenticate or force re-login
            // For now, we'll just return success. User will need to log in again.
            $this->renderApiJson(['status' => 'success', 'message' => 'Password changed successfully'], 200);
        }else {
            error_log("DEBUG: Change Password - FAILED to update password in DB for user ID: " . $userId);
            $this->renderApiJson(['status' => 'error', 'message' => 'Failed to change password'], 500);
        }
    }


    /**
     * Get all addresses for the authenticated user.
     * GET /api/user/addresses
     */
    public function getAddresses()
    {
        $userId = $this->authService->getAuthenticatedUserId();

        if (!$userId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        $addresses = $this->userAddressModel->findByUserId($userId);
        $this->renderApiJson(['status' => 'success', 'addresses' => $addresses], 200);
    }

    /**
     * Add a new address for the authenticated user.
     * POST /api/user/addresses
     */
    public function addAddress()
    {
        $userId = $this->authService->getAuthenticatedUserId();

        if (!$userId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Rules adapted to match the provided user_addresses table structure
        // ADDED: first_name, last_name, address2, phone
        $rules = [
            'title' => 'required|string|min:2|max:100',
            'first_name' => 'required|string|min:2|max:100', // ADDED
            'last_name' => 'required|string|min:2|max:100',  // ADDED
            'country' => 'required|string|min:2|max:100',
            'state' => 'required|string|min:2|max:100',
            'city' => 'required|string|min:2|max:100',
            'address' => 'required|string|min:5|max:255',
            'address2' => 'nullable|string|max:255', // ADDED
            'postal_code' => 'required|string|min:4|max:20',
            'phone' => 'nullable|string|max:20', // ADDED
            'is_default' => 'nullable|boolean',
        ];

        $this->validationService->validate($input, $rules);

        if (!$this->validationService->passes()) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed', 'errors' => $this->validationService->getErrors()], 422);
            return;
        }

        // Sanitize data adapted to match the provided user_addresses table structure
        // ADDED: first_name, last_name, address2, phone
        $sanitizedData = [
            'user_id' => $userId,
            'title' => $this->securityService->sanitizeInput($input['title']),
            'first_name' => $this->securityService->sanitizeInput($input['first_name']), // ADDED
            'last_name' => $this->securityService->sanitizeInput($input['last_name']),    // ADDED
            'country' => $this->securityService->sanitizeInput($input['country']),
            'state' => $this->securityService->sanitizeInput($input['state']),
            'city' => $this->securityService->sanitizeInput($input['city']),
            'address' => $this->securityService->sanitizeInput($input['address']),
            'address2' => $this->securityService->sanitizeInput($input['address2'] ?? null), // ADDED
            'postal_code' => $this->securityService->sanitizeInput($input['postal_code']),
            'phone' => $this->securityService->sanitizeInput($input['phone'] ?? null), // ADDED
            'is_default' => (bool)($input['is_default'] ?? false),
        ];

        // Handle setting as default: if new address is default, unset others
        if ($sanitizedData['is_default']) {
            $this->userAddressModel->setDefault($userId, 0); // Unset all others first (0 for address_id means no specific address, just unset defaults)
        }

        $addressId = $this->userAddressModel->createAddress($sanitizedData);

        if ($addressId) {
            $this->renderApiJson(['status' => 'success', 'message' => 'Address added successfully', 'address_id' => $addressId], 201);
        } else {
            $this->renderApiJson(['status' => 'error', 'message' => 'Failed to add address'], 500);
        }
    }

    /**
     * Get a specific address for the authenticated user.
     * GET /api/user/addresses/{id}
     *
     * @param int $id The ID of the address.
     */
    public function getAddress(int $id)
    {
        $userId = $this->authService->getAuthenticatedUserId();

        if (!$userId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        $address = $this->userAddressModel->findById($id);

        if (!$address || $address['user_id'] !== $userId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Address not found or unauthorized'], 404);
            return;
        }

        $this->renderApiJson(['status' => 'success', 'address' => $address], 200);
    }

    /**
     * Update a specific address for the authenticated user.
     * PUT /api/user/addresses/{id}
     *
     * @param int $id The ID of the address to update.
     */
    public function updateAddress(int $id)
    {
        $userId = $this->authService->getAuthenticatedUserId();

        if (!$userId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // First, verify the address belongs to the user
        $existingAddress = $this->userAddressModel->findById($id);
        if (!$existingAddress || $existingAddress['user_id'] !== $userId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Address not found or unauthorized'], 404);
            return;
        }

        // Rules adapted to match the provided user_addresses table structure
        // ADDED: first_name, last_name, address2, phone
        $rules = [
            'title' => 'required|string|min:2|max:100',
            'first_name' => 'required|string|min:2|max:100', // ADDED
            'last_name' => 'required|string|min:2|max:100',  // ADDED
            'country' => 'required|string|min:2|max:100',
            'state' => 'required|string|min:2|max:100',
            'city' => 'required|string|min:2|max:100',
            'address' => 'required|string|min:5|max:255',
            'address2' => 'nullable|string|max:255', // ADDED
            'postal_code' => 'required|string|min:4|max:20',
            'phone' => 'nullable|string|max:20', // ADDED
            'is_default' => 'nullable|boolean',
        ];

        $this->validationService->validate($input, $rules);

        if (!$this->validationService->passes()) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed', 'errors' => $this->validationService->getErrors()], 422);
            return;
        }

        // Sanitize data adapted to match the provided user_addresses table structure
        // ADDED: first_name, last_name, address2, phone
        $sanitizedData = [
            'title' => $this->securityService->sanitizeInput($input['title']),
            'first_name' => $this->securityService->sanitizeInput($input['first_name']), // ADDED
            'last_name' => $this->securityService->sanitizeInput($input['last_name']),    // ADDED
            'country' => $this->securityService->sanitizeInput($input['country']),
            'state' => $this->securityService->sanitizeInput($input['state']),
            'city' => $this->securityService->sanitizeInput($input['city']),
            'address' => $this->securityService->sanitizeInput($input['address']),
            'address2' => $this->securityService->sanitizeInput($input['address2'] ?? null), // ADDED
            'postal_code' => $this->securityService->sanitizeInput($input['postal_code']),
            'phone' => $this->securityService->sanitizeInput($input['phone'] ?? null), // ADDED
            'is_default' => (bool)($input['is_default'] ?? false),
        ];

        // Handle setting as default: if this address is set to default, unset others
        if ($sanitizedData['is_default']) {
            $this->userAddressModel->setDefault($userId, $id);
        }
        if ($this->userAddressModel->updateAddress($id, $sanitizedData)) {
            $this->renderApiJson(['status' => 'success', 'message' => 'Address updated successfully'], 200);
        } else {
            $this->renderApiJson(['status' => 'error', 'message' => 'Failed to update address'], 500);
        }
    }

    /**
     * Delete a specific address for the authenticated user.
     * DELETE /api/user/addresses/{id}
     *
     * @param int $id The ID of the address to delete.
     */
    public function deleteAddress(int $id)
    {
        $userId = $this->authService->getAuthenticatedUserId();

        if (!$userId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        // First, verify the address belongs to the user
        $existingAddress = $this->userAddressModel->findById($id);
        if (!$existingAddress || $existingAddress['user_id'] !== $userId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Address not found or unauthorized'], 404);
            return;
        }

        if ($this->userAddressModel->deleteAddress($id)) {
            $this->renderApiJson(['status' => 'success', 'message' => 'Address deleted successfully'], 200);
        } else {
            $this->renderApiJson(['status' => 'error', 'message' => 'Failed to delete address'], 500);
        }
    }

    /**
     * Get a list of games purchased by the authenticated user.
     * This will be used for the "My Game Library" / "Cloud Saves" feature.
     * GET /api/user/games
     */
    public function getPurchasedGames()
    {
        $userId = $this->authService->getAuthenticatedUserId();

        if (!$userId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        // Fetch ALL purchased games (including duplicates if purchased multiple times)
        // The User model now returns all relevant order items for a user.
        $allPurchasedGames = $this->userModel->getPurchasedProducts($userId);

        if ($allPurchasedGames === false) { // Check for potential database errors
            $this->renderApiJson(['message' => 'An error occurred while fetching your games.'], 500);
            return;
        }

        // --- PHP Logic to filter for unique games and get the latest purchase details ---
        $uniqueGames = [];
        foreach ($allPurchasedGames as $game) {
            $productId = $game['product_id'];
            // Use product_id as the key to ensure uniqueness
            // If the game is not yet in uniqueGames, or if the current game is newer (based on order_date or order_item_id)
            // we add/replace it. Since getPurchasedProducts already orders by created_at DESC,
            // the first occurrence of each product_id will be the latest.
            if (!isset($uniqueGames[$productId])) {
                $uniqueGames[$productId] = $game;
            }
            // If there's a need to explicitly get the *latest* by date/id,
            // and the SQL ORDER BY isn't strictly guaranteeing it for all cases (e.g., same date, different order_item_id)
            // you could add a comparison here. But with `ORDER BY o.created_at DESC, oi.id DESC` in the model,
            // the first encountered for each product_id should be the latest.
        }

        // Re-index the array for JSON output
        $uniqueGames = array_values($uniqueGames);

        // --- Manual Pagination in PHP (since we fetched all to filter) ---
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 12;
        $offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT) ?: 0;

        $paginatedGames = array_slice($uniqueGames, $offset, $limit);
        $totalUniqueGames = count($uniqueGames);

        $this->renderApiJson([
            'status' => 'success',
            'total' => $totalUniqueGames, // Total count of unique games
            'data' => $paginatedGames,    // Paginated unique games
            'limit' => $limit,
            'offset' => $offset
        ], 200);
    }
}
