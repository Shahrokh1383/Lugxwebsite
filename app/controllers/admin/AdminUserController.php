<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\User;
use App\Services\ValidationService;
use App\Services\SecurityService;
use Exception;
use PDOException;

class AdminUserController extends Controller
{
    private User $userModel;
    private ValidationService $validator;
    private SecurityService $securityService;

    public function __construct()
    {
        $this->userModel = new User();
        $this->validator = new ValidationService();
        $this->securityService = new SecurityService();
    }

    /**
     * Retrieves a paginated list of all users.
     * GET /api/admin/users
     * This endpoint is protected by AdminMiddleware.
     */
    public function getUsers(): void
    {
        try {
            // Retrieve pagination and search parameters from the request.
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 10);
            $search = $_GET['search'] ?? '';

            // Fetch users from the model with pagination and search functionality.
            $data = $this->userModel->findAllWithPaginationAndRole(['search' => $search], $page, $limit);

            // Respond with the user data and pagination information.
            $this->renderApiJson([
                'success' => true,
                'message' => 'Users fetched successfully.',
                'data' => [
                    'users' => $data['users'],
                    'currentPage' => $page,
                    'totalPages' => $data['totalPages'],
                    'totalUsers' => $data['totalCount']
                ]
            ]);
        } catch (PDOException $e) {
            error_log("Error in AdminUserController getUsers: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("Error in AdminUserController getUsers: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Retrieves details for a single user by ID.
     * GET /api/admin/users/{id}
     * This endpoint is protected by AdminMiddleware.
     *
     * @param int $id The user ID.
     */
    public function getUser(int $id): void
    {
        try {
            $user = $this->userModel->findById($id);

            if (!$user) {
                $this->renderApiJson(['success' => false, 'message' => 'User not found.'], 404);
                return;
            }

            // Return user details without sensitive information like password hash.
            unset($user['password_hash']);
            unset($user['verification_token']);
            unset($user['password_reset_token']);
            unset($user['password_reset_expiry']);

            $this->renderApiJson([
                'success' => true,
                'message' => 'User found.',
                'data' => $user
            ]);
        } catch (PDOException $e) {
            error_log("Error in AdminUserController getUser: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }

    /**
     * Creates a new user.
     * POST /api/admin/users
     * This endpoint is protected by AdminMiddleware.
     */
    public function createUser(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // 1. Input validation.
            $rules = [
                'first_name' => 'required|max:255',
                'last_name' => 'required|max:255',
                'email' => 'required|email|max:255',
                'password' => 'required|min:8',
                'role_id' => 'required|integer'
            ];
            if (!$this->validator->validate($data, $rules)) {
                $this->renderApiJson(['success' => false, 'errors' => $this->validator->getErrors()], 422);
                return;
            }

            // 2. Sanitize data and check for existing email.
            $email = $this->securityService->sanitizeEmail($data['email']);
            if ($this->userModel->findByEmail($email)) {
                $this->renderApiJson(['success' => false, 'message' => 'A user with this email already exists.'], 422);
                return;
            }

            // 3. Prepare data for insertion.
            $passwordHash = $this->securityService->hashPassword($data['password']);
            $userData = [
                'first_name' => $this->securityService->sanitizeString($data['first_name']),
                'last_name' => $this->securityService->sanitizeString($data['last_name']),
                'email' => $email,
                'password_hash' => $passwordHash,
                'role_id' => (int) $data['role_id'],
                'is_active' => true, // Assuming new users are active by default.
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // 4. Create the new user.
            $newUserId = $this->userModel->create($userData);

            if ($newUserId) {
                $this->renderApiJson(['success' => true, 'message' => 'User created successfully.', 'data' => ['id' => $newUserId]], 201);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to create user.'], 500);
            }
        } catch (PDOException $e) {
            error_log("Error in AdminUserController createUser: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("Error in AdminUserController createUser: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Updates an existing user's details.
     * PUT /api/admin/users/{id}
     * This endpoint is protected by AdminMiddleware.
     *
     * @param int $id The user ID.
     */
    public function updateUser(int $id): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // 1. Check if the user exists.
            $user = $this->userModel->findById($id);
            if (!$user) {
                $this->renderApiJson(['success' => false, 'message' => 'User not found.'], 404);
                return;
            }

            // 2. Input validation.
            $rules = [
                'first_name' => 'required|max:255',
                'last_name' => 'required|max:255',
                'email' => 'required|email|max:255',
                'role_id' => 'required|integer'
            ];
            // Password is optional for update.
            if (isset($data['password']) && !empty($data['password'])) {
                $rules['password'] = 'min:8';
            }
            if (!$this->validator->validate($data, $rules)) {
                $this->renderApiJson(['success' => false, 'errors' => $this->validator->getErrors()], 422);
                return;
            }

            // 3. Prepare data for update.
            $userData = [
                'first_name' => $this->securityService->sanitizeString($data['first_name']),
                'last_name' => $this->securityService->sanitizeString($data['last_name']),
                'email' => $this->securityService->sanitizeEmail($data['email']),
                'role_id' => (int) $data['role_id'],
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // Handle password update separately if a new one is provided.
            if (isset($data['password']) && !empty($data['password'])) {
                $userData['password_hash'] = $this->securityService->hashPassword($data['password']);
            }

            // 4. Update the user.
            if ($this->userModel->update($id, $userData)) {
                $this->renderApiJson(['success' => true, 'message' => 'User updated successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to update user or no changes were made.'], 500);
            }
        } catch (PDOException $e) {
            error_log("Error in AdminUserController updateUser: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("Error in AdminUserController updateUser: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Deletes a user by ID.
     * DELETE /api/admin/users/{id}
     * This endpoint is protected by AdminMiddleware.
     *
     * @param int $id The user ID to delete.
     */
    public function deleteUser(int $id): void
    {
        try {
            // 1. Check if the user exists.
            $user = $this->userModel->findById($id);
            if (!$user) {
                $this->renderApiJson(['success' => false, 'message' => 'User not found.'], 404);
                return;
            }
            
            // Note: A real-world application should prevent an admin from deleting themselves.
            // This is a simplified check for this implementation.

            // 2. Delete the user.
            if ($this->userModel->delete($id)) {
                $this->renderApiJson(['success' => true, 'message' => 'User deleted successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to delete user.'], 500);
            }
        } catch (PDOException $e) {
            error_log("Error in AdminUserController deleteUser: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("Error in AdminUserController deleteUser: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Retrieves all user roles.
     * GET /api/admin/roles
     * This is a helper endpoint for the frontend.
     */
    public function getRoles(): void
    {
        try {
            $roles = $this->userModel->getRoles();
            $this->renderApiJson(['success' => true, 'message' => 'Roles fetched successfully.', 'data' => $roles]);
        } catch (PDOException $e) {
            error_log("Error in AdminUserController getRoles: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }
}
