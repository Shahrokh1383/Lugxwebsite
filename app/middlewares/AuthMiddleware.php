<?php
namespace App\Middlewares;

use App\Services\AuthService;
use Exception; // It's good practice to include Exception for general error handling

class AuthMiddleware
{
    private AuthService $authService;

    public function __construct()
    {
        error_log("DEBUG: AuthMiddleware::construct - Initializing AuthMiddleware");
        $this->authService = new AuthService();
    }

    /**
     * Handles authentication check.
     * If user is not logged in, redirects to login page or sends JSON error for AJAX requests.
     *
     * @return bool True if authentication passes, false otherwise (request terminated/redirected).
     */
    public function handle(): bool
    {
        error_log("DEBUG: AuthMiddleware::handle - Checking authentication for URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

        // --- ADDED DEBUGGING FOR HEADERS_SENT ---
        if (headers_sent($file, $line)) {
            error_log("CRITICAL ERROR: Headers already sent from {$file}:{$line} BEFORE AuthMiddleware::handle() could set them. Premature output detected.");
            // We'll still proceed with the logic, but this log is a warning.
            // If it's too late to set headers, the JSON/redirect won't work as intended.
        }
        // --- END ADDED DEBUGGING ---

        if (!$this->authService->isLoggedIn()) {
            error_log("DEBUG: AuthMiddleware::handle - User is NOT logged in.");

            $isAjaxRequest = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
            // This line for $isApiRequest is correct here because $_SERVER['REQUEST_URI'] contains the full path.
            $isApiRequest = str_starts_with($_SERVER['REQUEST_URI'] ?? '', BASE_URL . '/api/'); 

            if ($isAjaxRequest || $isApiRequest) {
                error_log("DEBUG: AuthMiddleware::handle - Request is AJAX or API. Sending 401 JSON response.");
                
                if (ob_get_level() > 0) {
                    ob_end_clean(); // Discard any buffered output before sending JSON error
                }
                
                // --- ADDED DEBUGGING FOR HEADERS_SENT AFTER CLEANING ---
                if (headers_sent($file, $line)) { // Check again after cleaning buffers
                    error_log("CRITICAL ERROR: Headers already sent from {$file}:{$line} AFTER ob_end_clean() in AuthMiddleware. Cannot send JSON 401.");
                    // Fallback: If headers are sent, at least try to echo JSON
                    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Headers sent prematurely.']);
                    exit;
                }
                // --- END ADDED DEBUGGING ---

                header('Content-Type: application/json');
                http_response_code(401); // 401 Unauthorized
                echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
                exit; // Terminate script execution
            } else {
                error_log("DEBUG: AuthMiddleware::handle - Request is regular browser. Redirecting to login.");
                
                if (ob_get_level() > 0) {
                    ob_end_clean(); // Clean buffer before redirect
                }

                // --- ADDED DEBUGGING FOR HEADERS_SENT BEFORE REDIRECT ---
                if (headers_sent($file, $line)) { // Check before redirecting
                    error_log("CRITICAL ERROR: Headers already sent from {$file}:{$line} BEFORE redirect in AuthMiddleware. Cannot redirect.");
                    // Fallback: Display simple message if redirect impossible
                    echo "<h1>Unauthorized</h1><p>Please log in.</p>";
                    exit;
                }
                // --- END ADDED DEBUGGING ---

                $redirectUrl = BASE_URL . '/frontend/login.html?return_to=' . urlencode($_SERVER['REQUEST_URI'] ?? '/');
                header('Location: ' . $redirectUrl, true, 302); // 302 Found is standard for temporary redirect
                exit; // Terminate script execution
            }
        }
        error_log("DEBUG: AuthMiddleware::handle - Authentication passed for user ID: " . $this->authService->getAuthenticatedUserId());
        return true; // Authentication passed, continue to controller
    }
}
