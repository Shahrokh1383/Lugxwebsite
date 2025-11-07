<?php
namespace App\Controllers\Admin;
use App\Core\Controller;
use App\Models\Category;
use App\Services\AuthService;
use App\Services\UploadService;
use App\Services\ValidationService;
use PDO;
use Exception;
class AdminCategoryController extends Controller
{
    private Category $categoryModel;
    private AuthService $authService;
    private ValidationService $validator;
    
    public function __construct()
    {
        $this->categoryModel = new Category();
        $this->authService = new AuthService();
        $this->validator = new ValidationService();
    }
    
    //-------------------------------------------------------------
    // View Management
    //-------------------------------------------------------------
    /**
     * Renders the static HTML view for managing categories.
     * GET /admin/categories
     */
    public function index(): void
    {
        // This is a view handler. Authentication is handled by a middleware.
        $this->renderHtmlView('frontend/admin/admin_categories.html');
    }
    
    //-------------------------------------------------------------
    // API Endpoints
    // All API methods are protected by an authentication check.
    //-------------------------------------------------------------
    /**
     * Get a list of all categories.
     * GET /api/admin/categories
     */
    public function indexApi(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        try {
            $categories = $this->categoryModel->all();
            $this->renderApiJson([
                'success' => true,
                'data' => $categories,
                'message' => 'Categories fetched successfully.'
            ]);
        } catch (Exception $e) {
            error_log("Error fetching categories: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Get details for a single category.
     * GET /api/admin/categories/{id}
     *
     * @param int $id
     */
    public function show(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        try {
            $category = $this->categoryModel->findById($id);
            if (!$category) {
                $this->renderApiJson([
                    'success' => false,
                    'error' => 'Category not found.'
                ], 404);
                return;
            }
            
            $this->renderApiJson([
                'success' => true,
                'data' => $category,
                'message' => 'Category fetched successfully.'
            ]);
        } catch (Exception $e) {
            error_log("Error fetching category: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Create a new category.
     * POST /api/admin/categories
     */
    public function store(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        // Get form data instead of JSON data
        $data = $_POST;
        
        // Convert is_active to boolean if it exists
        if (isset($data['is_active'])) {
            $data['is_active'] = (bool)$data['is_active'];
        }
        
        // Validation rules
        $rules = [
            'name' => 'required|max:255',
            'slug' => 'required|max:255|unique:categories,slug',
            'description' => 'max:1000',
            'parent_id' => 'nullable|integer',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'meta_title' => 'max:255',
            'meta_description' => 'max:500'
        ];
        
        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $this->validator->getErrors()
            ], 400);
            return;
        }
        
        try {
            $categoryData = [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'parent_id' => isset($data['parent_id']) && $data['parent_id'] !== '' ? (int)$data['parent_id'] : null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null
            ];
            
            // Handle image upload if present
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadService = new UploadService();
                $imagePath = $uploadService->uploadFile($_FILES['image'], 'categories');
                if ($imagePath) {
                    $categoryData['image'] = $imagePath;
                }
            }
            
            $categoryId = $this->categoryModel->create($categoryData);
            
            if ($categoryId) {
                $category = $this->categoryModel->findById($categoryId);
                $this->renderApiJson([
                    'success' => true,
                    'data' => $category,
                    'message' => 'Category created successfully!'
                ], 201);
            } else {
                $this->renderApiJson([
                    'success' => false,
                    'error' => 'Failed to create category.'
                ], 500);
            }
        } catch (Exception $e) {
            error_log("Error creating category: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Update an existing category.
     * PUT /api/admin/categories/{id}
     *
     * @param int $id
     */
    public function update(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        // Parse form data properly for both PUT and POST requests
        $data = [];
        
        // Check if this is form data (multipart/form-data)
        if (strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
            // Parse multipart form data
            $data = $_POST;
        } else {
            // For non-multipart requests, try to get data from php://input
            $rawData = file_get_contents('php://input');
            parse_str($rawData, $data);
        }
        
        // Convert is_active to boolean if it exists
        if (isset($data['is_active'])) {
            $data['is_active'] = (bool)$data['is_active'];
        }
        
        // Validation rules
        $rules = [
            'name' => 'required|max:255',
            'slug' => 'required|max:255|unique:categories,slug,' . $id,
            'description' => 'max:1000',
            'parent_id' => 'nullable|integer',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'meta_title' => 'max:255',
            'meta_description' => 'max:500'
        ];
        
        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson([
                'success' => false,
                'error' => 'Validation failed.',
                'errors' => $this->validator->getErrors()
            ], 400);
            return;
        }
        
        try {
            $categoryData = [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'parent_id' => isset($data['parent_id']) && $data['parent_id'] !== '' ? (int)$data['parent_id'] : null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null
            ];
            
            // Handle image upload if present
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadService = new UploadService();
                $imagePath = $uploadService->uploadFile($_FILES['image'], 'categories');
                if ($imagePath) {
                    $categoryData['image'] = $imagePath;
                }
            }
            
            // Handle image removal if requested
            if (isset($data['remove_image']) && $data['remove_image'] === '1') {
                $categoryData['image'] = null;
            }
            
            if ($this->categoryModel->update($id, $categoryData)) {
                $category = $this->categoryModel->findById($id);
                $this->renderApiJson([
                    'success' => true,
                    'data' => $category,
                    'message' => 'Category updated successfully!'
                ]);
            } else {
                $this->renderApiJson([
                    'success' => false,
                    'error' => 'Failed to update category. It might not exist.'
                ], 500);
            }
        } catch (Exception $e) {
            error_log("Error updating category: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Delete a category.
     * DELETE /api/admin/categories/{id}
     *
     * @param int $id
     */
    public function destroy(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        try {
            // Check if category has children
            $children = $this->categoryModel->getChildren($id);
            if (!empty($children)) {
                $this->renderApiJson([
                    'success' => false,
                    'error' => 'Cannot delete category with child categories. Please delete child categories first.'
                ], 400);
                return;
            }
            
            // Check if category is used in products
            $productCount = $this->categoryModel->getProductCount($id);
            if ($productCount > 0) {
                $this->renderApiJson([
                    'success' => false,
                    'error' => "Cannot delete category. It is used in {$productCount} products."
                ], 400);
                return;
            }
            
            if ($this->categoryModel->delete($id)) {
                $this->renderApiJson([
                    'success' => true,
                    'message' => 'Category deleted successfully!'
                ]);
            } else {
                $this->renderApiJson([
                    'success' => false,
                    'error' => 'Failed to delete category. It might not exist.'
                ], 500);
            }
        } catch (Exception $e) {
            error_log("Error deleting category: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Get top-level categories for dropdowns.
     * GET /api/admin/categories/top-level
     */
    public function getTopLevel(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        try {
            $categories = $this->categoryModel->getTopLevel();
            $this->renderApiJson([
                'success' => true,
                'data' => $categories,
                'message' => 'Top-level categories fetched successfully.'
            ]);
        } catch (Exception $e) {
            error_log("Error fetching top-level categories: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
}