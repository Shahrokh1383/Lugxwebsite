<?php
namespace App\Models;

use App\Core\Model;
use PDOException;
use PDO;

class Menu extends Model
{
    protected string $table = 'menus';
    protected array $fillable = ['name', 'location', 'items', 'is_active'];

    /**
     * Retrieves a menu by its location.
     * @param string $location The location of the menu (e.g., 'main_nav', 'footer_links').
     * @return array|false The menu record, or false if not found.
     */
    public function findMenuByLocation(string $location): array|false
    {
        return $this->first(['location' => $location, 'is_active' => true]);
    }

    /**
     * Retrieves all menus.
     * @return array An array of all menu records.
     */
    public function getAllMenus(): array
    {
        try {
            return $this->all();
        } catch (PDOException $e) {
            error_log("Error fetching all menus: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Creates a new menu.
     * @param array $data The data for the new menu.
     * @return int|false The ID of the new menu, or false on failure.
     */
    public function createMenu(array $data): int|false
    {
        // JSON encode the 'items' array before saving
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = json_encode($data['items']);
        }
        return $this->create($data);
    }

    /**
     * Updates an existing menu.
     * @param int $id The ID of the menu to update.
     * @param array $data The data to update.
     * @return bool True on success, false on failure.
     */
    public function updateMenu(int $id, array $data): bool
    {
        // JSON encode the 'items' array before saving
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = json_encode($data['items']);
        }
        return $this->update($id, $data);
    }

    /**
     * Deletes a menu.
     * @param int $id The ID of the menu to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteMenu(int $id): bool
    {
        return $this->delete($id);
    }
}