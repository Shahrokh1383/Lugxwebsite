<?php
namespace App\Models;

use App\Core\Model;
use PDOException;

class UserAddress extends Model
{
    protected string $table = 'user_addresses';

    /**
     * Creates a new user address.
     *
     * @param array $data Associative array of address data.
     * @return int|false The ID of the new address or false on failure.
     */
    public function createAddress(array $data)
    {
        return $this->create($data);
    }

    /**
     * Finds an address by its ID.
     *
     * @param int $id The address ID.
     * @return array|false Address data or false if not found.
     */
    public function findById(int $id): array|false
    {
        return $this->first(['id' => $id]);
    }

    /**
     * Finds all addresses for a specific user.
     *
     * @param int $userId The ID of the user.
     * @return array An array of address data.
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM " . $this->table . " WHERE user_id = :user_id ORDER BY is_default DESC, created_at DESC");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Updates an existing user address.
     *
     * @param int $id The ID of the address to update.
     * @param array $data Associative array of data to update.
     * @return bool True on success, false on failure.
     */
    public function updateAddress(int $id, array $data): bool
    {
        return $this->update($id, $data);
    }

    /**
     * Deletes a user address.
     *
     * @param int $id The ID of the address to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteAddress(int $id): bool
    {
        return $this->delete($id);
    }

     /**
     * Sets a specific address as default for a user and unsets others.
     *
     * @param int $userId The ID of the user.
     * @param int $addressId The ID of the address to set as default.
     * @return bool True on success, false on failure.
     */
    public function setDefault(int $userId, int $addressId): bool 
    {
        try {
            // Unset all other addresses as default for this user
            $stmt1 = $this->db->prepare("UPDATE " . $this->table . " SET is_default = 0 WHERE user_id = :user_id");
            $stmt1->execute([':user_id' => $userId]);

            // Set the specified address as default
            $stmt2 = $this->db->prepare("UPDATE " . $this->table . " SET is_default = 1 WHERE id = :id AND user_id = :user_id");
            $stmt2->execute([':id' => $addressId, ':user_id' => $userId]);

            return true;
        }catch (\PDOException $e) {
            error_log("Error setting default address: " . $e->getMessage());
            return false;
        }
    }
}
