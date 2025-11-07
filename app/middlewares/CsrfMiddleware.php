<?php
namespace App\Middlewares;

use App\Services\SecurityService;

class CsrfMiddleware
{
    protected SecurityService $securityService;

    public function __construct()
    {
        $this->securityService = new SecurityService();
    }

    /**
     * Handles CSRF token validation for POST, PUT, DELETE requests.
     *
     * @return bool True if validation passes or if it's not a relevant request method, false otherwise (and exits).
     */
    public function handle(): bool
    {
        error_log("DEBUG: CsrfMiddleware::handle - Request Method: " . $_SERVER['REQUEST_METHOD']);

        // Only validate for POST, PUT, DELETE requests
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            $token = '';
            $tokenSource = 'none';

            // 1. Try to get token from X-CSRF-TOKEN header (recommended for AJAX)
            if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
                $tokenSource = 'X-CSRF-TOKEN Header';
            }
            // 2. Fallback: Try to get token from JSON body for application/json requests
            else if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                // Ensure CSRF_TOKEN_NAME is defined and matches the key in JSON
                $token = $data[CSRF_TOKEN_NAME] ?? '';
                $tokenSource = 'JSON Body';
            }
            // 3. Fallback: Try to get token from $_POST for form-urlencoded requests
            else if (isset($_POST[CSRF_TOKEN_NAME])) {
                $token = $_POST[CSRF_TOKEN_NAME];
                $tokenSource = 'POST Data';
            }

            error_log("DEBUG: CsrfMiddleware: Received token: '{$token}' from {$tokenSource}");
            error_log("DEBUG: CsrfMiddleware: Session token (from \$_SESSION[CSRF_TOKEN_NAME]): '" . ($_SESSION[CSRF_TOKEN_NAME] ?? 'NOT SET') . "'");
            error_log("DEBUG: CsrfMiddleware: Session token expiry: " . ($_SESSION[CSRF_TOKEN_NAME . '_expire'] ?? 'NOT SET') . ", Current time: " . time());


            if (!$this->securityService->validateCsrfToken($token)) {
                error_log("DEBUG: CsrfMiddleware: CSRF token validation FAILED for method " . $_SERVER['REQUEST_METHOD'] . " to URI " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

                if (ob_get_level() > 0) {
                    ob_end_clean();
                }

                http_response_code(403); // Forbidden
                if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'CSRF token mismatch.']);
                } else {
                    if (defined('ROOT_PATH') && file_exists(ROOT_PATH . '/views/errors/403.php')) {
                        require_once ROOT_PATH . '/views/errors/403.php';
                    } else {
                        echo "Error 403: Forbidden (CSRF Token Mismatch)";
                    }
                }
                exit();
            }
            error_log("DEBUG: CsrfMiddleware: CSRF token validation PASSED for method " . $_SERVER['REQUEST_METHOD'] . " to URI " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        }
        return true;
    }
}
