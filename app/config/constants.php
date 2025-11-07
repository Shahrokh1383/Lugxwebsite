<?php
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Dynamically determine the base URL for the application.
// This logic aims to correctly handle installations in both domain root and subdirectories.
// However, for consistency with .env, we will prioritize BASE_URL from .env if set.
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptName = $_SERVER['SCRIPT_NAME']; // Example: /Lugxwebsite/public/index.php or /index.php

// Calculate the base URI by removing '/public/index.php' or '/index.php' from the script path.
// This ensures $baseUri is something like '/Lugxwebsite' or '/'
$baseUri = str_replace(['/index.php', '/public'], '', $scriptName);
// Ensure the base URI always starts with a single slash and remove any trailing slashes.
$baseUri = '/' . trim($baseUri, '/');
// If, after trimming, baseUri becomes empty, it implies the app is at the domain root (e.g., http://localhost/).
// In such cases, ensure it's simply '/'.
$baseUri = ($baseUri === '//' || $baseUri === '') ? '/' : $baseUri;

// Define the complete base URL including protocol, host, and base URI.
// Prioritize BASE_URL from environment variables if it's set.
define('BASE_URL', getenv('BASE_URL') ?: ($protocol . '://' . $host . $baseUri));

// Define the path component of BASE_URL. This is crucial for routing.
// Example: If BASE_URL is http://localhost:8080/Lugxwebsite, then BASE_URL_PATH is /Lugxwebsite
// If BASE_URL is http://localhost:8080, then BASE_URL_PATH is /
define('BASE_URL_PATH', parse_url(BASE_URL, PHP_URL_PATH) ?: '/');


// Define constants related to CSRF (Cross-Site Request Forgery) protection.
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_EXPIRE_TIME', 3600); // CSRF token validity period in seconds (1 hour).

// General application constants.
// Prioritize APP_NAME from environment variables if it's set.
define('APP_NAME', getenv('APP_NAME') ?: 'Lugxwebsite'); // Changed to read from getenv()
// Determine the application environment. Defaults to 'development' if not set via environment variables.
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // Options: 'development', 'production'.

// Define paths for file uploads.
define('UPLOAD_DIR', PUBLIC_PATH . '/uploads'); // Physical server path where files are stored.
define('UPLOAD_URL', BASE_URL . '/public/uploads'); // Public URL to access uploaded files via browser.
// Corrected UPLOAD_URL: It should point to BASE_URL + /public/uploads because public is the served directory.
// If BASE_URL is http://localhost:8080/Lugxwebsite, then UPLOAD_URL needs to be http://localhost:8080/Lugxwebsite/public/uploads

// Configure PHP error reporting based on the application environment.
if (APP_ENV === 'development') {
    ini_set('display_errors', 1); // IMPORTANT: Display errors in development for debugging.
    ini_set('display_startup_errors', 1); // Display startup errors.
    error_reporting(E_ALL); // Report all PHP errors.
} else {
    ini_set('display_errors', 0); // Hide errors from the browser.
    ini_set('display_startup_errors', 0); // Hide startup errors.
    error_reporting(0); // Disable all error reporting in production (errors should be logged).
}
