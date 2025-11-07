<?php
namespace App\Controllers\Api;
use App\Core\Controller;
use App\Services\AuthService;
use App\Services\SecurityService;

class AuthController extends Controller
{
    private AuthService $authService;
    private SecurityService $securityService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->securityService = new SecurityService();
    }

    /**
     * Handles user registration via API.
     * Expects JSON input: { "username", "email", "password", "confirm_password", "first_name", "last_name", ... }
     * Route: POST /api/register
     */
    public function register(): void
    {
        // Get JSON input from the request body.
        $data = json_decode(file_get_contents('php://input'), true);

        // Call the AuthService to handle the registration logic.
        $response = $this->authService->register($data);

        // Render JSON response. Use 200 for success, 400 for client-side errors.
        $this->renderApiJson($response, $response['status'] === 'success' ? 200 : 400);
    }

    /**
     * Handles email verification via API.
     * Expects token in query string: GET /api/verify-email?token={token}
     * This endpoint will redirect to a frontend page after verification.
     */
    public function verifyEmail(): void 
    {
        $token = $_GET['token'] ?? ''; // Get token from URL query parameter.

        // Call the AuthService to handle email verification.
        $response = $this->authService->verifyEmail($token);

        // Redirect to a frontend page to display verification result to the user.
        if ($response['status'] === 'success') {
            $this->redirect('/public/frontend/login.html?verified=true'); // Redirect to login with success.
        } else if ($response['status'] === 'info') {
             $this->redirect('/public/frontend/login.html?verified=already'); // Redirect to login with info.
        } else {
            $this->redirect('/public/frontend/register.html?error=verification_failed'); // Redirect to register with error.
        }
    }

     /**
     * Handles user login via API.
     * Expects JSON input: { "email", "password", "remember_me" (optional) }
     * Route: POST /api/login
     */
    public function login(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $rememberMe = (bool)($data['remember_me'] ?? false);

        $response = $this->authService->login($email, $password, $rememberMe);

        // Use 200 for success, 401 (Unauthorized) for failed authentication.
        $this->renderApiJson($response, $response['status'] === 'success' ? 200 : 401);
    }

    /**
     * Handles user logout via API.
     * Route: POST /api/logout
     */
    public function logout(): void 
    {
        if ($this->authService->logout()) {
            $this->renderApiJson(['status' => 'success', 'message' => 'Logged out successfully.']);
        } else {
            $this->renderApiJson(['status' => 'error', 'message' => 'Logout failed.'], 500); // Internal Server Error.
        }
    }

    /**
     * Handles forgot password request via API (sends reset email).
     * Expects JSON input: { "email" }
     * Route: POST /api/forgot-password
     */
    public function forgotPassword(): void 
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';

        // Call AuthService. Always return 200 OK to prevent email enumeration.
        $response = $this->authService->forgotPassword($email);
        $this->renderApiJson($response, 200);
    }

    /**
     * Handles password reset via API.
     * Expects JSON input: { "token", "new_password", "confirm_new_password" }
     * Route: POST /api/reset-password
     */
    public function resetPassword(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $token = $data['token'] ?? '';
        // FIX: Typo in variable names - should be 'new_password' and 'confirm_new_password'
        $newPassword  = $data['new_password'] ?? ''; // Corrected from $data['newPassword ']
        $confirmNewPassword  = $data['confirm_new_password'] ?? ''; // Corrected from $data['confirmNewPassword ']

        $response = $this->authService->resetPassword($token, $newPassword, $confirmNewPassword);

        // Use 200 for success, 400 for client-side errors (e.g., invalid token, password mismatch).
        $this->renderApiJson($response, $response['status'] === 'success' ? 200 : 400);
    }

     /**
     * Provides the current user's session status via API.
     * Route: GET /api/auth/status
     */
    public function status():void 
    {
        if($this->authService->isLoggedIn()) {
            $this->renderApiJson(['status' => 'success', 'logged_in' => true, 'user' => $this->authService->getCurrentUser()]);
        } else {
            $this->renderApiJson(['status' => 'success', 'logged_in' => false]);
        }
    }

    /**
     * Provides a CSRF token for frontend forms via API.
     * Route: GET /api/csrf-token
     */
    public function getCsrfToken(): void 
    {
        try {
            // Generate the CSRF token using your SecurityService
            $token = $this->securityService->generateCsrfToken();
            
            // Return the token as a JSON object with a success status
            $this->renderApiJson(['status' => 'success', 'csrf_token' => $token], 200);
        } catch (\Throwable $th) {
            // In case of any error during token generation, return an error response
            error_log("CRITICAL ERROR: AuthController::getCsrfToken - Failed to generate CSRF token: " . $th->getMessage());
            $this->renderApiJson(['status' => 'error', 'message' => 'Failed to generate token.'], 500);
        }
    }

}
