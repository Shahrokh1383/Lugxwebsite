<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Wishlist;
use App\Services\ValidationService;

class WishlistController extends Controller
{
    private Wishlist $wishlistModel;
    private ValidationService $validationService;

    public function __construct()
    {
        parent::__construct();
        $this->wishlistModel = new Wishlist();
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
     * Add a product to the user's wishlist.
     * POST /api/wishlist/add
     * Request Body: { "product_id": int }
     */
    public function add(): void
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

        // Validate incoming data
        if (!$this->validationService->validate($data, [
            'product_id' => 'required|integer|min:1'
        ])) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $this->validationService->getErrors()], 400);
            return;
        }

        $productId = (int)$data['product_id'];

        $success = $this->wishlistModel->add($userId, $productId);

        if ($success) {
            $this->renderApiJson(['status' => 'success', 'message' => 'Product added to wishlist successfully.'], 200);
        } else {
            // This could be a database error or product not found/invalid
            $this->renderApiJson(['status' => 'error', 'message' => 'Failed to add product to wishlist. It might already be there or product is invalid.'], 400);
        }
    }

    /**
     * Remove a product from the user's wishlist.
     * DELETE /api/wishlist/remove
     * Request Body: { "product_id": int }
     */
    public function remove(): void
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

        // Validate incoming data
        if (!$this->validationService->validate($data, [
            'product_id' => 'required|integer|min:1'
        ])) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $this->validationService->getErrors()], 400);
            return;
        }

        $productId = (int)$data['product_id'];

        $success = $this->wishlistModel->remove($userId, $productId);

        if ($success) {
            $this->renderApiJson(['status' => 'success', 'message' => 'Product removed from wishlist successfully.'], 200);
        } else {
            // This could be a database error
            $this->renderApiJson(['status' => 'error', 'message' => 'Failed to remove product from wishlist.'], 400);
        }
    }

    /**
     * Get the current contents of the user's wishlist.
     * GET /api/wishlist
     */
    public function getWishlist(): void
    {
        try {
            $userId = $this->getAuthenticatedUserId();
        } catch (\Exception $e) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Unauthorized. Please log in.'], 401);
            return;
        }

        $wishlistItems = $this->wishlistModel->getWishlistContents($userId);

        // Changed 'data' structure to directly return the array of items,
        // matching frontend's wishlist.js expectation (result.data as an array).
        $this->renderApiJson([
            'status' => 'success',
            'message' => 'Wishlist contents retrieved successfully.',
            'data' => $wishlistItems // Changed from ['items' => $wishlistItems]
        ], 200);
    }
}
