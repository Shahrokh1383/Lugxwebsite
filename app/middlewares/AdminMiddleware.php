<?php
namespace App\Middlewares;

use App\Services\AuthService;
use App\Models\User;
use Exception;

class AdminMiddleware
{
    private AuthService $authService;
    private User $userModel;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userModel = new User();
    }

    /**
     * Handles the authentication check specifically for admin users.
     *
     * @return bool True if the user is a logged-in admin, false otherwise.
     */
    public function handle(): bool
    {
        // 1. Check if the user is logged in.
        if (!$this->authService->isLoggedIn()) {
            error_log("DEBUG: AdminMiddleware::handle - User is NOT logged in. Unauthorized.");

            $this->respondWithUnauthorized();
            return false;
        }

        // 2. User is logged in, now check for the 'admin' role.
        $userId = $this->authService->getAuthenticatedUserId();
        if (!$this->userModel->hasRole($userId, 'admin')) {
            // User is logged in but does not have the 'admin' role.
            error_log("DEBUG: AdminMiddleware::handle - User (ID: {$userId}) does not have 'admin' role. Forbidden.");
            
            $this->respondWithForbidden();
            return false;
        }

        // 3. Authentication and role check passed.
        error_log("DEBUG: AdminMiddleware::handle - Access granted for admin user (ID: {$userId}).");
        return true; // Continue to the next middleware or controller.
    }

    /**
     * Responds to an unauthorized request (not logged in).
     * This method either sends a JSON 401 response or redirects to the login page.
     */
    private function respondWithUnauthorized(): void
    {
        // Improved detection of API requests by checking the Accept header
        $isApiRequest = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isApiRequest) {
            header('Content-Type: application/json');
            http_response_code(401); // 401 Unauthorized
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
        } else {
            // Redirect to the admin login page for regular browser requests.
            $redirectUrl = BASE_URL . '/frontend/admin/admin_login.html?return_to=' . urlencode($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: ' . $redirectUrl, true, 302);
        }
        exit;
    }

    /**
     * Responds to a forbidden request (logged in but not an admin).
     * This method either sends a JSON 403 response or redirects to a forbidden page (or login).
     */
    private function respondWithForbidden(): void
    {
        // Improved detection of API requests by checking the Accept header
        $isApiRequest = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        
        if ($isApiRequest) {
            header('Content-Type: application/json');
            http_response_code(403); // 403 Forbidden
            echo json_encode(['status' => 'error', 'message' => 'Forbidden. You do not have permission to access this resource.']);
        } else {
            // In a browser, we can show a forbidden page or redirect to login.
            // Redirecting to login is a simple and common practice.
            $redirectUrl = BASE_URL . '/frontend/admin/admin_login.html';
            header('Location: ' . $redirectUrl, true, 302);
        }
        exit;
    }
}