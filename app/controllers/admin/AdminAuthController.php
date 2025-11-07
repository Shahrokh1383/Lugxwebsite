<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\User;
use App\Services\ValidationService;
use App\Services\AuthService;
use App\Services\SecurityService;

class AdminAuthController extends Controller
{
    private ValidationService $validator;
    private User $userModel;
    private AuthService $authService;
    private SecurityService $securityService;

    public function __construct()
    {
        // Initializes services and models needed for authentication.
        $this->validator = new ValidationService();
        $this->userModel = new User();
        $this->authService = new AuthService();
        $this->securityService = new SecurityService();
    }

    /**
     * Handles admin login API request.
     * POST /api/admin/auth/login
     */
    public function login(): void
    {
        // Start a new session if one is not already active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Get the POST data from the request body.
        $data = json_decode(file_get_contents('php://input'), true);

        // 1. Validate the input data with the ValidationService.
        $rules = [
            'email' => 'required|email|max:255',
            'password' => 'required|min:8'
        ];

        if (!$this->validator->validate($data, $rules)) {
            // If validation fails, return a JSON response with errors.
            $this->renderApiJson([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors()
            ], 422);
            return;
        }

        $email = $this->securityService->sanitizeEmail($data['email']);
        $password = $data['password'];
        $rememberMe = $data['remember_me'] ?? false; // Assuming a 'remember_me' flag is sent

        // 2. Delegate the login logic to the AuthService.
        $loginResult = $this->authService->login($email, $password, (bool)$rememberMe);

        if ($loginResult['status'] === 'success') {
            // Check if the authenticated user has the 'admin' role.
            if ($this->authService->hasRole('admin')) {
                // If login is successful and the user is an admin, return a success response.
                $this->renderApiJson([
                    'success' => true,
                    'message' => 'Login successful. Redirecting to the admin dashboard.'
                ]);
            } else {
                // Log out the non-admin user and deny access.
                $this->authService->logout();
                $this->renderApiJson([
                    'success' => false,
                    'message' => 'Access denied. You are not an administrator.'
                ], 403);
            }
        } else {
            // If login fails for any reason (e.g., invalid credentials), return the error message.
            $this->renderApiJson([
                'success' => false,
                'message' => $loginResult['message']
            ], 401);
        }
    }

    /**
     * Handles admin logout API request.
     * POST /api/admin/auth/logout
     */
    public function logout(): void
    {
        // Start a new session if one is not already active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Log out the current user by destroying the session.
        $this->authService->logout();
        
        $this->renderApiJson([
            'success' => true,
            'message' => 'You have been logged out successfully.'
        ]);
    }
}
