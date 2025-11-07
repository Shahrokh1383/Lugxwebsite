<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class User extends Model
{
    protected string $table = 'users';

    /**
     * Find a user by their email address.
     *
     * @param string $email
     * @return array|false Returns user data as an associative array, or false if not found.
     */
    public function findByEmail(string $email): array|false
    {
        return $this->first(['email' => $email]);
    }

    /**
     * Find a user by their username.
     *
     * @param string $username
     * @return array|false Returns user data as an associative array, or false if not found.
     */
    public function findByUsername(string $username): array|false
    {
        return $this->first(['username' => $username]);
    }

    /**
    * Fetches a list of all users with their roles, with optional search and pagination.
    * This method is essential for the admin user management page.
    *
    * @param array $filters An associative array of search filters (e.g., ['search' => 'john']).
    * @param int $page The current page number for pagination.
    * @param int $limit The number of records per page.
    * @return array An array containing 'users' data and 'totalCount' for pagination.
    */
    public function findAllWithPaginationAndRole(array $filters = [], int $page = 1, int $limit = 10): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $search = $filters['search'] ?? '';

            $sql = "
                SELECT
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.is_active,
                    u.created_at,
                    ur.name AS role_name
                FROM users u
                JOIN user_roles ur ON u.role_id = ur.id
            ";

            $params = [];
            if ($search) {
                $sql .= " WHERE u.first_name LIKE :search_first_name OR u.last_name LIKE :search_last_name OR u.email LIKE :search_email ";
                $params[':search_first_name'] = '%' . $search . '%';
                $params[':search_last_name'] = '%' . $search . '%';
                $params[':search_email'] = '%' . $search . '%'; 
            }

            $sql .= "ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);

            //Bind parameters
            foreach ($params as $key => &$value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }

            // Bind pagination parameters.
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count for pagination.
            $countSql = "SELECT COUNT(*) FROM users u";
            if ($search) {
                $countSql .= " WHERE u.first_name LIKE :search_first_name OR u.last_name LIKE :search_last_name OR u.email LIKE :search_email ";
            }
            $countStmt = $this->db->prepare($countSql);

            // Bind search parameters for count query.
            foreach ($params as $key => &$value) {
                $countStmt->bindValue($key, $value, PDO::PARAM_STR);
            }

            $countStmt->execute();
            $totalCount = (int) $countStmt->fetchColumn();

            return [
                'users' => $users,
                'totalCount' => $totalCount,
                'totalPages' => ceil($totalCount / $limit)
            ];
        }catch (PDOException $e) {
            error_log("Error fetching users with pagination: " . $e->getMessage());
            return ['users' => [], 'totalCount' => 0, 'totalPages' => 0];
        }
    }


    /**
     * Fetches all available user roles from the database.
     * This is useful for populating role dropdowns in the admin panel.
     *
     * @return array An array of user roles.
     */
    public function getRoles(): array
    {
        try {
            $stmt = $this->db->prepare("SELECT id, name FROM user_roles ORDER BY name ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching user roles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a user by their ID.
     * This method is crucial and should exist. Assuming it uses the generic find method from Model.
     *
     * @param int $id The user ID.
     * @return array|false User data or false if not found.
     */
    public function findById(int $id): array|false
    {
        return $this->first(['id' => $id]);
    }

    /**
     * Update a user's email verification status and verification token.
     *
     * @param int $userId The ID of the user to update.
     * @param bool $isVerified Boolean indicating if email is verified.
     * @param string|null $verificationToken The token to set, or null if verified.
     * @return bool True on success, false on failure.
     */
    public function updateEmailVerificationStatus(int $userId, bool $isVerified, ?string $verificationToken): bool
    {
        $data = [
            'email_verified' => $isVerified,
            'verification_token' => $verificationToken
        ];
        return $this->update($userId, $data);
    }

    /**
     * Update a user's password reset token and its expiry.
     *
     * @param int $userId The ID of the user to update.
     * @param string|null $token The reset token, or null to clear.
     * @param string|null $expiry The expiry datetime string (YYYY-MM-DD HH:MM:SS), or null to clear.
     * @return bool True on success, false on failure.
     */
    public function updatePasswordResetToken(int $userId, ?string $token, ?string $expiry): bool
    {
        $data = [
            'password_reset_token' => $token,
            'password_reset_expiry' => $expiry
        ];
        return $this->update($userId, $data);
    }

    /**
     * Get user by password reset token.
     *
     * @param string $token
     * @return array|false
     */
    public function findByResetToken(string $token): array|false
    {
        return $this->first(['password_reset_token' => $token]);
    }

    /**
     * Check if a user has a specific role by role name.
     * This method assumes 'role_id' is stored in the users table and references 'user_roles.id'.
     *
     * @param int $userId
     * @param string $roleName The name of the role (e.g., 'admin', 'customer').
     * @return bool
     */
    public function hasRole(int $userId, string $roleName): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(u.id)
                FROM users u
                JOIN user_roles ur ON u.role_id = ur.id
                WHERE u.id = :user_id AND ur.name = :role_name
                LIMIT 1
            ");
            $stmt->execute([':user_id' => $userId, ':role_name' => $roleName]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error checking user role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates user profile information.
     *
     * @param int $userId The ID of the user to update.
     * @param array $data Associative array of data to update (e.g., first_name, last_name, phone, date_of_birth, gender, avatar).
     * @return bool True on success, false on failure.
     */
    public function updateProfile(int $userId , array $data): bool
    {
        return $this->update($userId , $data);
    }

    /**
     * Updates a user's password hash in the database.
     *
     * @param int $userId The ID of the user whose password to change.
     * @param string $newPasswordHash The new hashed password.
     * @return bool True on success, false on failure.
     */
    public function changePassword(int $userId, string $newPasswordHash): bool
    {
        return $this->update($userId, ['password_hash' => $newPasswordHash]);
    }

    /**
     * Check if user has purchased a product
     * 
     * @param int $userId User ID
     * @param int $productId Product ID
     * @return bool True if user has purchased the product
     */
    public function hasUserPurchasedProduct(int $userId, int $productId): bool
    {
        $sql = "SELECT COUNT(*) as count 
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE oi.product_id = :product_id AND o.user_id = :user_id AND o.payment_status = 'paid'";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
    return $result['count'] > 0;
    }

    /**
     * Gets a list of ALL purchased products for a specific user, including duplicates if purchased multiple times.
     * The uniqueness logic will be handled in the controller.
     * @param int $userId The ID of the user.
     * @param int|null $limit Optional limit for pagination (applied after PHP filtering).
     * @param int $offset Optional offset for pagination (applied after PHP filtering).
     * @return array List of purchased product data including license keys.
     */
    public function getPurchasedProducts(int $userId, ?int $limit = null, int $offset = 0): array
    {
        try {
            // Simplified query: fetch all relevant data, including duplicates.
            // Uniqueness logic will be handled in PHP in the controller.
            $query = "
                SELECT
                    p.id AS product_id,
                    p.title AS product_title,
                    p.slug AS product_slug,
                    p.featured_image AS product_image,
                    p.price AS product_price,
                    p.sale_price AS product_sale_price,
                    p.average_rating AS product_average_rating,
                    pk.license_key,
                    oi.download_link,
                    oi.id AS order_item_id,
                    o.order_number,
                    o.created_at AS order_date
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                JOIN product_keys pk ON oi.id = pk.order_item_id
                WHERE o.user_id = :user_id
                AND o.status IN ('delivered', 'processing')
                AND pk.is_used = TRUE
                ORDER BY o.created_at DESC, oi.id DESC -- Order to ensure latest is first if we filter in PHP
            ";

            // Note: LIMIT and OFFSET are NOT applied here. They will be applied in PHP after filtering duplicates.
            // This is because applying LIMIT/OFFSET here would limit the *raw* results, not the unique ones.

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all purchased products for user (for PHP filtering): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gets the total count of UNIQUE products purchased by a specific user.
     * This method will also be modified to reflect the PHP-side uniqueness.
     * For now, we'll keep it simple and count distinct products from the raw data.
     * @param int $userId The ID of the user.
     * @return int Total count of unique purchased products.
     */
    public function countPurchasedProducts(int $userId): int
    {
        try {
            // This count will reflect the *raw* distinct products.
            // The actual count of *unique* games after PHP filtering might be different.
            // For true unique count, we'd need to fetch all and count in PHP,
            // or use a complex SQL query that works on all MySQL versions.
            // For simplicity, we'll count distinct product IDs directly here.
            $query = "
                SELECT COUNT(DISTINCT p.id)
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                JOIN product_keys pk ON oi.id = pk.order_item_id
                WHERE o.user_id = :user_id
                AND o.status IN ('delivered', 'processing')
                AND pk.is_used = TRUE
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting purchased products for user: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetches the total number of users.
     * @return int The total count of users.
     */
    public function countAll(): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table}");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        }catch (PDOException $e) {
            error_log("Error counting all users: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetches the most recent users.
     * @param int $limit The number of recent users to fetch.
     * @return array An array of recent user data.
     */
    public function findLast(int $limit = 5): array
    {
        try {
            $stmt = $this->db->prepare("SELECT id, first_name, last_name, email, created_at FROM {$this->table} ORDER BY created_at DESC LIMIT :limit");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching recent users: " . $e->getMessage());
            return [];
        }
    }
}
