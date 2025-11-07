<?php
namespace App\Services;

use App\Models\User;
use App\Models\UserRole;
use App\Services\SecurityService;
use App\Services\ValidationService;
use App\Services\MailService;
use Exception; // Ensure Exception is imported for general error handling

class AuthService
{
    private User $userModel;
    private UserRole $userRoleModel;
    private SecurityService $securityService;
    private ValidationService $validationService;
    private MailService $mailService;

    public function __construct()
    {
        error_log("DEBUG: AuthService::construct - Initializing dependencies");
        $this->userModel = new User();
        $this->userRoleModel = new UserRole();
        $this->securityService = new SecurityService();
        $this->validationService = new ValidationService();
        $this->mailService = new MailService();
    }

    /**
     * Register a new user account.
     *
     * @param array $data Contains 'username', 'email', 'password', 'confirm_password', 'first_name', 'last_name', etc.
     * @return array Status and message or errors.
     */
    public function register(array $data): array
    {
        error_log("DEBUG: AuthService::register - Attempting user registration.");
        // Sanitize input
        $username = $this->securityService->sanitizeString($data['username'] ?? '');
        $email = $this->securityService->sanitizeEmail($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        $firstName = $this->securityService->sanitizeString($data['first_name'] ?? '');
        $lastName = $this->securityService->sanitizeString($data['last_name'] ?? '');
        $phone = $this->securityService->sanitizeString($data['phone'] ?? '');
        $dateOfBirth = $this->securityService->sanitizeString($data['date_of_birth'] ?? '');
        $gender = $this->securityService->sanitizeString($data['gender'] ?? '');

        // Validate input
        $this->validationService->required($username, 'username', 'Username is required.');
        $this->validationService->minLength($username, 'username', 3, 'Username must be at least 3 characters long.');
        $this->validationService->maxLength($username, 'username', 50, 'Username cannot exceed 50 characters.');

        $this->validationService->required($email, 'email', 'Email is required.');
        $this->validationService->email($email, 'email', 'Invalid email format.');

        $this->validationService->required($password, 'password', 'Password is required.');
        $this->validationService->minLength($password, 'password', 8, 'Password must be at least 8 characters long.');
        $this->validationService->matches($password, $confirmPassword, 'confirm_password', 'Passwords do not match.');

        $this->validationService->required($firstName, 'first_name', 'First name is required.');
        $this->validationService->maxLength($firstName, 'first_name', 50, 'First name cannot exceed 50 characters.');

        $this->validationService->required($lastName, 'last_name', 'Last name is required.');
        $this->validationService->maxLength($lastName, 'last_name', 50, 'Last name cannot exceed 50 characters.');

        if (!$this->validationService->passes()) {
            error_log("DEBUG: AuthService::register - Validation failed at initial stage.");
            return ['status' => 'error', 'message' => 'Validation failed', 'errors' => $this->validationService->getErrors()];
        }

        // Check if username or email already exists
        if ($this->userModel->findByUsername($username)) {
            $this->validationService->addError('username', 'Username already taken.');
        }
        if ($this->userModel->findByEmail($email)) {
            $this->validationService->addError('email', 'Email already registered.');
        }

        if (!$this->validationService->passes()) {
            error_log("DEBUG: AuthService::register - Validation failed at uniqueness check.");
            return ['status' => 'error', 'message' => 'Validation failed', 'errors' => $this->validationService->getErrors()];
        }

        // Get 'customer' role ID (assuming 'customer' role has id 3 based on your data)
        $customerRole = $this->userRoleModel->findByName('customer');
        if (!$customerRole) {
            error_log("ERROR: AuthService::register - Default 'customer' role not found in database. Please ensure it's inserted.");
            return ['status' => 'error', 'message' => 'System error during registration. Please contact support.'];
        }

        // Hash password and generate verification token
        $hashedPassword = $this->securityService->hashPassword($password);
        try {
            $verificationToken = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            error_log("ERROR: AuthService::register - Failed to generate verification token: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Registration failed due to a server error (token generation).'];
        }

        $userData = [
            'username' => $username,
            'email' => $email,
            'password_hash' => $hashedPassword,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => !empty($phone) ? $phone : null,
            'date_of_birth' => !empty($dateOfBirth) ? $dateOfBirth : null,
            'gender' => !empty($gender) ? $gender : null,
            'role_id' => $customerRole['id'],
            'email_verified' => false,
            'is_active' => true,
            'verification_token' => $verificationToken,
        ];

        $userId = $this->userModel->create($userData);

        if ($userId) {
            error_log("DEBUG: AuthService::register - User created successfully with ID: {$userId}. Attempting to send verification email.");
            // Send verification email
            if ($this->mailService->sendVerificationEmail($email, $username, $verificationToken)) {
                error_log("DEBUG: AuthService::register - Verification email sent successfully to {$email}.");
                return ['status' => 'success', 'message' => 'Registration successful! Please check your email to verify your account.'];
            } else {
                error_log("ERROR: AuthService::register - Failed to send verification email to {$email} for user ID {$userId}.");
                return ['status' => 'success', 'message' => 'Registration successful, but failed to send verification email. Please contact support if you do not receive it.'];
            }
        }
        error_log("ERROR: AuthService::register - User creation failed in model.");
        return ['status' => 'error', 'message' => 'Registration failed due to a server error.'];
    }

    /**
     * Verify user's email address using the provided token.
     *
     * @param string $token The email verification token.
     * @return array Status and message.
     */
    public function verifyEmail(string $token): array
    {
        error_log("DEBUG: AuthService::verifyEmail - Attempting to verify email with token: {$token}.");
        if (empty($token)) {
            error_log("DEBUG: AuthService::verifyEmail - Verification token is missing.");
            return ['status' => 'error', 'message' => 'Verification token is missing.'];
        }

        $user = $this->userModel->first(['verification_token' => $token]);

        if (!$user) {
            error_log("DEBUG: AuthService::verifyEmail - Invalid or expired verification token: {$token}.");
            return ['status' => 'error', 'message' => 'Invalid or expired verification token.'];
        }

        if ($user['email_verified']) {
            error_log("DEBUG: AuthService::verifyEmail - Email already verified for user ID: {$user['id']}.");
            return ['status' => 'info', 'message' => 'Email already verified.'];
        }

        if ($this->userModel->update($user['id'], [
            'email_verified' => true,
            'verification_token' => null,
        ])) {
            error_log("DEBUG: AuthService::verifyEmail - Email verified successfully for user ID: {$user['id']}.");
            return ['status' => 'success', 'message' => 'Email verified successfully! You can now log in.'];
        }

        error_log("ERROR: AuthService::verifyEmail - Email verification failed for user ID: {$user['id']}.");
        return ['status' => 'error', 'message' => 'Email verification failed. Please try again or contact support.'];
    }

    /**
     * Authenticate user and create session.
     *
     * @param string $email The user's email.
     * @param string $password The user's password.
     * @param bool $rememberMe Whether to keep the user logged in persistently.
     * @return array Status and message or user data.
     */
    public function login(string $email, string $password, bool $rememberMe = false): array
    {
        error_log("DEBUG: AuthService::login - Attempting login for email: " . $email);

        // Sanitize and validate input
        $email = $this->securityService->sanitizeEmail($email);
        $this->validationService->required($email, 'email', 'Email is required.');
        $this->validationService->email($email, 'email', 'Invalid email format.');
        $this->validationService->required($password, 'password', 'Password is required.');

        if (!$this->validationService->passes()) {
            error_log("DEBUG: AuthService::login - Validation failed.");
            return ['status' => 'error', 'message' => 'Validation failed', 'errors' => $this->validationService->getErrors()];
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            error_log("DEBUG: AuthService::login - User not found for email: " . $email);
            return ['status' => 'error', 'message' => 'Invalid email or password.'];
        }

        error_log("DEBUG: AuthService::login - User found (ID: {$user['id']}). Comparing password.");
        // For security, avoid logging raw passwords or hashes in production.
        // error_log("DEBUG: Login - Input password: " . $password);
        // error_log("DEBUG: Login - Stored password hash from DB: " . $user['password_hash']);

        if (!$this->securityService->verifyPassword($password, $user['password_hash'])) {
            error_log("DEBUG: AuthService::login - Password verification FAILED for email: " . $email);
            return ['status' => 'error', 'message' => 'Invalid email or password.'];
        }
        error_log("DEBUG: AuthService::login - Password verification SUCCESS for email: " . $email);

        // Check if account is active and email is verified
        if (!$user['is_active']) {
            error_log("DEBUG: AuthService::login - Account not active for user ID: " . $user['id']);
            return ['status' => 'error', 'message' => 'Your account is currently inactive. Please contact support.'];
        }
        if (!$user['email_verified']) {
            error_log("DEBUG: AuthService::login - Email not verified for user ID: " . $user['id']);
            return ['status' => 'error', 'message' => 'Your email has not been verified. Please check your inbox for the verification link.'];
        }

        // Update last login timestamp
        $this->userModel->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
        error_log("DEBUG: AuthService::login - Updated last_login for user ID: " . $user['id']);

        // Create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['logged_in_at'] = time();
        error_log("DEBUG: AuthService::login - Session created for user ID: " . $user['id']);

        // Handle remember me (future development: implement secure persistent login tokens)
        if ($rememberMe) {
            ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); // 30 days
            ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30);
            error_log("DEBUG: AuthService::login - Remember me enabled.");
        }

        return ['status' => 'success', 'message' => 'Logged in successfully!', 'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role_id' => $user['role_id']
        ]];
    }


    /**
     * Logs out the current user by destroying the session.
     *
     * @return bool True on successful logout.
     */
    public function logout(): bool
    {
        error_log("DEBUG: AuthService::logout - Attempting user logout.");
        // Clear all session variables
        $_SESSION = [];
        // Invalidate the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        // Destroy the session
        session_destroy();
        error_log("DEBUG: AuthService::logout - Session destroyed.");
        return true;
    }

    /**
     * Handles forgot password request by sending a reset email.
     *
     * @param string $email The email address for which to reset the password.
     * @return array Status and message. Always returns a generic success message to prevent email enumeration.
     */
    public function forgotPassword(string $email): array
    {
        error_log("DEBUG: AuthService::forgotPassword - Called for email: " . $email);

        $email = $this->securityService->sanitizeEmail($email);
        $this->validationService->required($email, 'email', 'Email is required.');
        $this->validationService->email($email, 'email', 'Invalid email format.');

        if (!$this->validationService->passes()) {
            error_log("DEBUG: AuthService::forgotPassword - Validation failed.");
            return ['status' => 'error', 'message' => 'Validation failed', 'errors' => $this->validationService->getErrors()];
        }

        $user = $this->userModel->findByEmail($email);

        if ($user) {
            error_log("DEBUG: AuthService::forgotPassword - User found for email: " . $email . " User ID: " . $user['id']);
            try {
                $token = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                error_log("ERROR: AuthService::forgotPassword - Failed to generate token: " . $e->getMessage());
                return ['status' => 'error', 'message' => 'Password reset failed due to server error (token generation).'];
            }
            $expiresAt = date('Y-m-d H:i:s', time() + (60 * 60)); // Token valid for 1 hour

            error_log("DEBUG: AuthService::forgotPassword - Generated token: " . $token . " Expiry: " . $expiresAt);

            $updateResult = $this->userModel->updatePasswordResetToken($user['id'], $token, $expiresAt);
            
            error_log("DEBUG: AuthService::forgotPassword - updatePasswordResetToken result: " . ($updateResult ? 'true' : 'false'));

            if ($updateResult) {
                error_log("DEBUG: AuthService::forgotPassword - Token saved successfully for user ID: " . $user['id']);
                if (!$this->mailService->sendResetPasswordEmail($email, $user['username'], $token)) {
                    error_log("ERROR: AuthService::forgotPassword - Failed to send password reset email to {$email}. Mailer service error.");
                } else {
                    error_log("DEBUG: AuthService::forgotPassword - Password reset email sent successfully to {$email}.");
                }
            } else {
                error_log("ERROR: AuthService::forgotPassword - Failed to save password reset token for user ID {$user['id']}.");
            }
        } else {
            error_log("DEBUG: AuthService::forgotPassword - User not found for email: " . $email . ". Returning generic success message.");
        }

        // Always return a generic success message to prevent email enumeration.
        return ['status' => 'success', 'message' => 'If your email is registered, a password reset link has been sent to your inbox.'];
    }

    /**
     * Resets user's password using a valid token.
     *
     * @param string $token The password reset token from the URL/form.
     * @param string $newPassword The new password.
     * @param string $confirmNewPassword Confirmation of the new password.
     * @return array Status and message.
     */
    public function resetPassword(string $token, string $newPassword, string $confirmNewPassword): array
    {
        error_log("DEBUG: AuthService::resetPassword - Attempting to reset password with token: {$token}.");
        if (empty($token)) {
            error_log("DEBUG: AuthService::resetPassword - Password reset token is missing.");
            return ['status' => 'error', 'message' => 'Password reset token is missing.'];
        }

        // Validate new password input
        $this->validationService->required($newPassword, 'new_password', 'New password is required.');
        $this->validationService->minLength($newPassword, 'new_password', 8, 'New password must be at least 8 characters long.');
        $this->validationService->matches($newPassword, $confirmNewPassword, 'confirm_new_password', 'New passwords do not match.');

        if (!$this->validationService->passes()) {
            error_log("DEBUG: AuthService::resetPassword - Validation failed.");
            return ['status' => 'error', 'message' => 'Validation failed', 'errors' => $this->validationService->getErrors()];
        }

        // Find user by reset token and check expiry
        $user = $this->userModel->findByResetToken($token);

        if (!$user || empty($user['password_reset_token']) || strtotime($user['password_reset_expiry']) < time()) {
            error_log("DEBUG: AuthService::resetPassword - Invalid or expired password reset token: {$token}.");
            return ['status' => 'error', 'message' => 'Invalid or expired password reset token.'];
        }
        error_log("DEBUG: AuthService::resetPassword - User found for token: {$token}. User ID: {$user['id']}.");

        // Update password and clear the token and expiry
        $hashedPassword = $this->securityService->hashPassword($newPassword);
        if ($this->userModel->update($user['id'], ['password_hash' => $hashedPassword])) {
            // Clear the token and expiry after successful reset
            $this->userModel->updatePasswordResetToken($user['id'], null, null);
            error_log("DEBUG: AuthService::resetPassword - Password successfully reset for user ID: {$user['id']}.");
            return ['status' => 'success', 'message' => 'Your password has been successfully reset.'];
        }

        error_log("ERROR: AuthService::resetPassword - Password reset failed for user ID: {$user['id']}.");
        return ['status' => 'error', 'message' => 'Password reset failed. Please try again.'];
    }

    /**
     * Check if a user is currently logged in based on session data.
     *
     * @return bool True if a user ID is present in the session, false otherwise.
     */
    public function isLoggedIn(): bool
    {
        $loggedIn = isset($_SESSION['user_id']);
        // error_log("DEBUG: AuthService::isLoggedIn - User logged in: " . ($loggedIn ? 'true' : 'false')); // Keep this commented unless needed for specific debug
        return $loggedIn;
    }

    /**
     * Get data for the currently logged-in user from the session.
     *
     * @return array|null An associative array of user session data, or null if no user is logged in.
     */
    public function getCurrentUser(): ?array
    {
        if ($this->isLoggedIn()) {
            error_log("DEBUG: AuthService::getCurrentUser - Retrieved user: " . json_encode([
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'role_id' => $_SESSION['role_id']
            ]));
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'role_id' => $_SESSION['role_id'],
            ];
        }
        error_log("DEBUG: AuthService::getCurrentUser - No user logged in.");
        return null;
    }

    /**
     * Get the ID of the currently authenticated user.
     * This is a new helper method for controllers.
     *
     * @return int|null The user ID or null if not authenticated.
     */
    public function getAuthenticatedUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Checks if the currently logged-in user has a specific role.
     *
     * @param string $roleName The name of the role (e.g., 'admin', 'customer').
     * @return bool
     */
    public function hasRole(string $roleName): bool
    {
        error_log("DEBUG: AuthService::hasRole - Checking role '{$roleName}'.");
        if (!$this->isLoggedIn()) {
            error_log("DEBUG: AuthService::hasRole - User not logged in.");
            return false;
        }
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            error_log("ERROR: AuthService::hasRole - Current user data not available despite being logged in.");
            return false;
        }
        // Delegate role check to the User model, which can perform a join.
        $hasRole = $this->userModel->hasRole($currentUser['id'], $roleName);
        error_log("DEBUG: AuthService::hasRole - User ID {$currentUser['id']} has role '{$roleName}': " . ($hasRole ? 'true' : 'false'));
        return $hasRole;
    }
}
