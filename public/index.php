<?php
// This MUST be the absolute first line of the file, with no spaces or newlines before it.
// Attempt to capture any output that might have occurred even before ob_start() or very early.
// This is a last resort to catch extremely early output.
$earlyOutput = '';
if (ob_get_length() > 0) {
    $earlyOutput = ob_get_contents();
    ob_clean(); // Clear the buffer to prevent double output
    error_log("DEBUG: EXTREMELY EARLY OUTPUT DETECTED: '" . bin2hex($earlyOutput) . "' (Hex representation)");
    // Optionally, write to a separate file if php_error.log is not showing it
    // file_put_contents('C:/xampp/php/logs/early_output.log', "EXTREMELY EARLY OUTPUT: '" . bin2hex($earlyOutput) . "'\n", FILE_APPEND);
}

// Start output buffering at the very beginning of the script.
ob_start();

// --- START DEBUGGING CHECK (This was already there, keeping it) ---
// Check if any output has been sent before this point.
if (ob_get_length() > 0) {
    $premature_output = ob_get_contents();
    ob_clean(); // Clear the buffer to prevent double output
    error_log("DEBUG: Premature output detected after ob_start() but before App init: '" . bin2hex($premature_output) . "' (Hex representation)");
}
// --- END DEBUGGING CHECK ---


// Define project root path. This must be the very first definition in the entire application
// as other files depend on it being defined before they are processed.
define('ROOT_PATH', dirname(__DIR__));

// Ensure Composer's autoloader is included very early. This is CRITICAL for PHP to locate
// and load classes defined with namespaces (e.g., App\Core\App, App\Core\Router).
// Without this, PHP will report "Class not found" errors for namespaced classes.
require_once ROOT_PATH . '/vendor/autoload.php';

// Load Dotenv after autoloader to make environment variables available via getenv().
// Make sure you have 'vlucas/phpdotenv' installed via Composer: composer require vlucas/phpdotenv
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->load();

// Load application constants. These define various configuration values and paths.
// They depend on ROOT_PATH being defined, so this line must come after ROOT_PATH definition.
require_once ROOT_PATH . '/app/config/constants.php';

// Start PHP session if it hasn't been started already.
// Session management is crucial for user authentication and state.
// This must happen before any output is sent to the browser.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

use App\Core\App; // Import the main App class from its namespace.

// Instantiate the core application class and run it.
// This kicks off the routing and request handling process.
$app = new App();
$app->run();

// Flush the output buffer at the very end of the script execution.
// This ensures all buffered content (including JSON) is sent to the browser.
// This line should be the absolute last line of the file, with no spaces or newlines after it.
ob_end_flush();
