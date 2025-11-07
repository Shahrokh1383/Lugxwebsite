<?php
namespace App\Models;

use App\Core\Model;
use PDOException;
use PDO;

class Banner extends Model
{
    protected string $table = 'banners';
    protected array $fillable = ['title', 'image', 'link', 'description', 'position', 'sort_order', 'start_date', 'end_date', 'is_active', 'click_count'];

    /**
     * Retrieves all banners from the database.
     * @return array An array of banner records.
     */
    public function getAllBanners(): array
    {
        try {
            return $this->all();
        } catch (PDOException $e) {
            error_log("Error fetching all banners: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Finds a banner by its ID.
     * @param int $id The ID of the banner.
     * @return array|false The banner record, or false if not found.
     */
    public function findBannerById(int $id): array|false
    {
        return $this->find($id);
    }
    
    /**
     * Creates a new banner record.
     * @param array $data The data for the new banner.
     * @return int|false The ID of the new banner, or false on failure.
     */
    public function createBanner(array $data): int|false
    {
        return $this->create($data);
    }

    /**
     * Updates an existing banner record.
     * @param int $id The ID of the banner to update.
     * @param array $data The data to update.
     * @return bool True on success, false on failure.
     */
    public function updateBanner(int $id, array $data): bool
    {
        return $this->update($id, $data);
    }

    /**
     * Deletes a banner by its ID.
     * @param int $id The ID of the banner to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteBanner(int $id): bool
    {
        return $this->delete($id);
    }
}
