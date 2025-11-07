<?php
namespace App\Services;

class SecurityService
{
    /**
     * Generates a CSRF token and stores it in the session.
     *
     * @return string
     */
    public function generateCsrfToken(): string
    {
        // Ensure session is started before accessing $_SESSION
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
            error_log("DEBUG: SecurityService::generateCsrfToken - session_start() called (was not active).");
        }

        if (empty($_SESSION[CSRF_TOKEN_NAME]) || ($_SESSION[CSRF_TOKEN_NAME . '_expire'] ?? 0) < time()) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
            $_SESSION[CSRF_TOKEN_NAME . '_expire'] = time() + CSRF_EXPIRE_TIME;
            error_log("DEBUG: SecurityService::generateCsrfToken - New CSRF token generated: " . $_SESSION[CSRF_TOKEN_NAME]);
        } else {
            error_log("DEBUG: SecurityService::generateCsrfToken - Using existing CSRF token: " . $_SESSION[CSRF_TOKEN_NAME]);
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /**
     * Validates a CSRF token.
     *
     * @param string $token
     * @return bool
     */
    public function validateCsrfToken(string $token): bool
    {
        // Ensure session is started before accessing $_SESSION
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
            error_log("DEBUG: SecurityService::validateCsrfToken - session_start() called (was not active).");
        }

        $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? null;
        $sessionTokenExpire = $_SESSION[CSRF_TOKEN_NAME . '_expire'] ?? 0;

        error_log("DEBUG: SecurityService::validateCsrfToken - Token received: '{$token}'");
        error_log("DEBUG: SecurityService::validateCsrfToken - Token in session: '" . ($sessionToken ?? 'NULL') . "'");
        error_log("DEBUG: SecurityService::validateCsrfToken - Session token expiry: {$sessionTokenExpire}, Current time: " . time());

        if (empty($sessionToken)) {
            error_log("DEBUG: SecurityService::validateCsrfToken - No token in session.");
            return false;
        }

        if ($sessionTokenExpire < time()) {
            error_log("DEBUG: SecurityService::validateCsrfToken - Token expired.");
            return false;
        }

        if (hash_equals($sessionToken, $token)) {
            error_log("DEBUG: SecurityService::validateCsrfToken - Token match SUCCESS.");
            return true;
        }
        error_log("DEBUG: SecurityService::validateCsrfToken - Token mismatch.");
        return false;
    }

    /**
     * Hashes a password using the default hashing algorithm.
     *
     * @param string $password
     * @return string
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verifies a password against a hash.
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Sanitizes a string input.
     *
     * @param string $input
     * @return string
     */
    public function sanitizeString(string $input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitizes an integer input.
     *
     * @param mixed $input
     * @return int|null
     */
    public function sanitizeInt(mixed $input): ?int
    {
        return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitizes an email input.
     *
     * @param string $input
     * @return string
     */
    public function sanitizeEmail(string $input): string
    {
        return filter_var($input, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitizes input data based on its type.
     * Use this for general sanitization in controllers.
     *
     * @param mixed $input The input data to sanitize.
     * @return mixed The sanitized data.
     */
    public function sanitizeInput(mixed $input): mixed
    {
        if (is_string($input)) {
            return $this->sanitizeString($input);
        }
        if (is_int($input) || is_numeric($input)) {
            return $this->sanitizeInt($input);
        }
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        return $input;
    }
}
