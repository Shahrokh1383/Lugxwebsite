<?php
namespace App\Core;

use PDO;
use PDOException;
use Exception; // Ensure Exception is imported for general error handling

class Controller
{
    // ADDED CONSTRUCTOR FOR DEBUGGING
    public function __construct()
    {
        error_log("DEBUG: Controller::__construct - Called."); // ADDED DEBUG LOG
        // You might want to initialize common services here in the future, e.g.:
        // $this->db = Database::getInstance();
        // $this->auth = new AuthService();
    }

    /**
     * Loads a model class.
     *
     * @param string $modelName The name of the model class (e.g., 'User').
     * @return object|null An instance of the model, or null if not found.
     */
    protected function model(string $modelName): ?object
    {
        error_log("DEBUG: Controller::model - Attempting to load model: " . $modelName); // ADDED DEBUG LOG
        $modelPath = ROOT_PATH . '/app/Models/' . $modelName . '.php'; // Corrected path for Models folder
        if (file_exists($modelPath)) {
            $className = 'App\\Models\\' . $modelName; // Full Qualified Class Name.
            if (class_exists($className)) {
                error_log("DEBUG: Controller::model - Instantiating model: " . $className); // ADDED DEBUG LOG
                try {
                    return new $className();
                } catch (\Throwable $e) { // Catch errors during model instantiation
                    error_log("ERROR: Controller::model - Failed to instantiate model '" . $className . "': " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
                    return null;
                }
            }
        }
        // Log the error if the model file or class is not found.
        error_log("ERROR: Controller::model - Model file not found or class not defined: " . $modelName . " at path: " . $modelPath); // IMPROVED LOG
        return null;
    }

    /**
     * Renders a view file. (Primarily for server-side rendered views, not for static HTML redirects).
     *
     * @param string $viewPath The path to the view file (e.g., 'errors.404' maps to views/errors/404.php).
     * @param array $data Data to be passed to the view, which will be extracted into variables.
     */
    protected function view(string $viewPath, array $data = []): void
    {
        error_log("DEBUG: Controller::view - Attempting to render view: " . $viewPath); // ADDED DEBUG LOG
        // Extract data array keys into variables for direct use in the view.
        extract($data);

        // Construct the full path to the view file.
        $viewFile = ROOT_PATH . '/views/' . str_replace('.', '/', $viewPath) . '.php'; // Allows dot notation for subdirectories.

        if (file_exists($viewFile)) {
            require_once $viewFile;
            error_log("DEBUG: Controller::view - View rendered: " . $viewFile); // ADDED DEBUG LOG
        } else {
            // Log if the view file is not found and redirect to a 404 page.
            error_log("ERROR: Controller::view - View file not found: " . $viewFile);
            $this->redirect('/404'); // Redirect to a general 404 page.
        }
    }

    /**
     * Renders a static HTML file, injecting BASE_URL_PATH for JavaScript.
     * This method is specifically designed for serving HTML files that contain JavaScript making AJAX calls,
     * allowing dynamic injection of base URL paths without changing file extensions.
     *
     * @param string $htmlFilePath The path to the HTML file relative to ROOT_PATH (e.g., 'public/frontend/admin/admin_login.html').
     * @param array $data Optional data to pass (not extracted, but useful for future expansion).
     */
    protected function renderHtmlView(string $htmlFilePath, array $data = []): void
    {
        error_log("DEBUG: Controller::renderHtmlView - Called for HTML file: " . $htmlFilePath);

        $fullPath = ROOT_PATH . '/' . $htmlFilePath;

        if (!file_exists($fullPath)) {
            error_log("ERROR: Controller::renderHtmlView - HTML view file not found: " . $fullPath);
            http_response_code(404);
            echo "<h1>404 Not Found</h1><p>The requested page could not be found.</p>";
            exit;
        }

        // Start output buffering specifically for this view rendering to capture its content.
        ob_start();
        require_once $fullPath; // Include the HTML file
        $content = ob_get_clean(); // Get content and clean the buffer

        // Inject BASE_URL_PATH into the HTML.
        // We'll use a placeholder like '<!-- APP_BASE_URL_PATH_PLACEHOLDER -->' in the HTML.
        // This allows JavaScript to access the correct base path for AJAX calls.
        $injectedScript = "<script>window.AppBaseUrlPath = '" . BASE_URL_PATH . "';</script>";
        // Find the closing </head> tag and insert the script just before it.
        // This ensures the JS variable is available before other scripts try to use it.
        $content = str_replace('</head>', $injectedScript . "\n</head>", $content);

        // Send the processed content to the browser.
        echo $content;
        error_log("DEBUG: Controller::renderHtmlView - HTML view successfully rendered: " . $htmlFilePath);
    }

    /**
     * Renders a JSON response for API endpoints.
     *
     * @param array $data The data to be encoded as JSON.
     * @param int $statusCode The HTTP status code to send with the response (default: 200 OK).
     */
    protected function renderApiJson(array $data, int $statusCode = 200): void
    {
        error_log("DEBUG: Controller::renderApiJson - Called with data: " . json_encode($data, JSON_THROW_ON_ERROR) . ", status: " . $statusCode);

        // Clear the current output buffer's content.
        // This is crucial to prevent any premature output (like whitespace) from interfering with headers.
        if (ob_get_level() > 0) {
            ob_clean(); 
        }

        // Check if headers have already been sent *after* clearing the current buffer.
        if (headers_sent($file, $line)) {
            error_log("CRITICAL ERROR: Headers already sent from {$file}:{$line} AFTER ob_clean() in renderApiJson. Premature output detected.");
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Internal server error: Headers already sent. Cannot guarantee JSON Content-Type.']);
            exit;
        }

        // Set Content-Type header and HTTP status code.
        header('Content-Type: application/json; charset=utf-8', true, $statusCode);
        
        try {
            // Encode and echo JSON data. This writes to the current active buffer (from index.php).
            echo json_encode($data, JSON_THROW_ON_ERROR);
        } catch (Exception $e) { // Use Exception for JSON encoding errors
            error_log("ERROR: Controller::renderApiJson - JSON encoding failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Internal server error: JSON encoding failed.']);
        }
        
        exit; // Terminate script
    }

    /**
     * Redirects the browser to a specified URL.
     *
     * @param string $url The URL to redirect to (relative to BASE_URL).
     * @param int $statusCode The HTTP status code for the redirect (default: 302 Found).
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        // Construct the full URL using BASE_URL defined in constants.php.
        // This method remains unchanged as per your request.
        $fullUrl = BASE_URL . $url;
        error_log("DEBUG: Controller::redirect - Redirecting to: " . $fullUrl);
        header('Location: ' . $fullUrl, true, $statusCode);
        exit; // Keep exit here as redirects should terminate script execution
    }

    /**
     * Get JSON data from the request body.
     * @return array
     */
    protected function getJsonData(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
}
