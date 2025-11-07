<?php
namespace App\Models;

use App\Core\Model;
use PDOException;
use PDO;

class Page extends Model
{
    protected string $table = 'pages';
    protected array $fillable = ['title', 'slug', 'content', 'excerpt', 'featured_image', 'status', 'template', 'meta_title', 'meta_description', 'created_by'];

    /**
     * Retrieves all pages from the database.
     * @return array An array of page records.
     */
    public function getAllPages(): array
    {
        try {
            return $this->all();
        } catch (PDOException $e) {
            error_log("Error fetching all pages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Finds a page by its ID.
     * @param int $id The ID of the page.
     * @return array|false The page record, or false if not found.
     */
    public function findPageById(int $id): array|false
    {
        return $this->find($id);
    }
    
    /**
     * Finds a page by its slug.
     * @param string $slug The slug of the page.
     * @return array|false The page record, or false if not found.
     */
    public function findPageBySlug(string $slug): array|false
    {
        return $this->first(['slug' => $slug]);
    }

    /**
     * Creates a new page record.
     * @param array $data The data for the new page.
     * @return int|false The ID of the new page, or false on failure.
     */
    public function createPage(array $data): int|false
    {
        return $this->create($data);
    }

    /**
     * Updates an existing page record.
     * @param int $id The ID of the page to update.
     * @param array $data The data to update.
     * @return bool True on success, false on failure.
     */
    public function updatePage(int $id, array $data): bool
    {
        return $this->update($id, $data);
    }

    /**
     * Deletes a page by its ID.
     * @param int $id The ID of the page to delete.
     * @return bool True on success, false on failure.
     */
    public function deletePage(int $id): bool
    {
        return $this->delete($id);
    }
}
