<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Category;

/**
 * Category API Controller
 * Handles category-related API requests.
 */
class CategoryController extends Controller
{
    private Category $categoryModel;

    public function __construct()
    {
        $this->categoryModel = $this->model('Category');

        if (!$this->categoryModel) {
            error_log("Failed to load Category model in CategoryController.");
            $this->renderApiJson(['message' => 'Internal server error: Category model could not be loaded.'], 500);
            exit; 
        }
    }

    /**
    * Get a list of categories.
    *
    * GET /api/categories
    * Example: /api/categories?limit=5
    */
    public function index(): void
    {
        // Sanitize and validate input for limit
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);

        $categories = $this->categoryModel->getAll($limit);

        if ($categories === false) {
            $this->renderApiJson(['message' => 'An error occurred while fetching categories.'], 500);
            return;
        }

        $this->renderApiJson([
            'status' => 'success',
            'total' => count($categories), // Total count based on fetched items (if limit is applied)
            'data' => $categories
        ]);
    }

    public function show(string $idOrSlug): void
    {
        $category = null;
        if (is_numeric($idOrSlug)) {
            $category = $this->categoryModel->findById((int)$idOrSlug);
        }else {
            $category = $this->categoryModel->findBySlug($idOrSlug);
        }
        if (!$category) {
            $this->renderApiJson(['message' => 'Category not found.'], 404);
            return;
        }
        $this->renderApiJson(['status' => 'success', 'data' => $category]);
    }
}
