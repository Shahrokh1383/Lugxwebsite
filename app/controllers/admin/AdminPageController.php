<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Page;
use App\Models\Menu;
use App\Services\AuthService;
use App\Services\ValidationService;
use App\Services\UploadService;
use PDOException;
use Exception;

class AdminPageController extends Controller
{
    private AuthService $authService;
    private Page $pageModel;
    private Menu $menuModel;
    private ValidationService $validator;
    private UploadService $uploadService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->pageModel = new Page();
        $this->menuModel = new Menu();
        $this->validator = new ValidationService();
        $this->uploadService = new UploadService();
    }

    /**
     * Renders the static HTML view for managing pages.
     * GET /admin/pages
     */
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->redirect('/admin/login');
            return;
        }
        
        $this->renderHtmlView('frontend/admin/admin_pages.html');
    }

    /**
     * Retrieves all pages for the admin panel.
     * GET /api/admin/pages
     */
    public function getPages(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $pages = $this->pageModel->getAllPages();
            $this->renderApiJson([
                'success' => true,
                'message' => 'Pages fetched successfully.',
                'data' => $pages
            ]);
        } catch (PDOException $e) {
            error_log("Database Error in getPages: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in getPages: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Retrieves a single page by ID.
     * GET /api/admin/pages/{id}
     *
     * @param int $id
     */
    public function getPage(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $page = $this->pageModel->findPageById($id);
            if ($page) {
                $this->renderApiJson(['success' => true, 'message' => 'Page found.', 'data' => $page]);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Page not found.'], 404);
            }
        } catch (PDOException $e) {
            error_log("Database Error in getPage: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }

    /**
     * Creates a new page.
     * POST /api/admin/pages
     */
    public function createPage(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }
    
        $data = $_POST;
        $file = $_FILES['featured_image'] ?? null;
    
        $rules = [
            'title' => 'required|max:255',
            'slug' => 'required|max:255|unique:pages',
            'content' => 'required',
            'status' => 'required|in:draft,published,private',
            'template' => 'max:100',
        ];
    
        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['success' => false, 'message' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }
    
        try {
            $imagePath = null;
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'public/uploads/pages/';
                $imagePath = $this->uploadService->uploadFile($file, $uploadDir);
                if ($imagePath === false) {
                    $this->renderApiJson(['success' => false, 'message' => 'Failed to upload image.'], 500);
                    return;
                }
            }
    
            $data['featured_image'] = $imagePath;
            $data['created_by'] = $this->authService->getCurrentUser()['id'];
            
            $newPageId = $this->pageModel->createPage($data);
    
            if ($newPageId) {
                $this->renderApiJson(['success' => true, 'message' => 'Page created successfully.', 'id' => $newPageId], 201);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to create page.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error creating page: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Updates an existing page.
     * POST /api/admin/pages/{id} (using POST with _method=PUT for file uploads)
     *
     * @param int $id
     */
    public function updatePage(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }
    
        $data = $_POST;
        $file = $_FILES['featured_image'] ?? null;
    
        $rules = [
            'title' => 'required|max:255',
            'slug' => 'required|max:255|unique:pages,slug,' . $id,
            'content' => 'required',
            'status' => 'required|in:draft,published,private',
            'template' => 'max:100',
        ];
    
        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['success' => false, 'message' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }
    
        try {
            $pageToUpdate = $this->pageModel->findPageById($id);
            if (!$pageToUpdate) {
                $this->renderApiJson(['success' => false, 'message' => 'Page not found.'], 404);
                return;
            }
    
            // Handle image upload if a new file is provided
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                // Delete old image if it exists
                if ($pageToUpdate['featured_image'] && file_exists(ROOT_PATH . '/' . $pageToUpdate['featured_image'])) {
                    unlink(ROOT_PATH . '/' . $pageToUpdate['featured_image']);
                }
                
                $uploadDir = 'public/uploads/pages/';
                $imagePath = $this->uploadService->uploadFile($file, $uploadDir);
                
                if ($imagePath === false) {
                    $this->renderApiJson(['success' => false, 'message' => 'Failed to upload new image.'], 500);
                    return;
                }
                $data['featured_image'] = $imagePath;
            } else {
                // Keep the old image path if no new file is uploaded
                $data['featured_image'] = $pageToUpdate['featured_image'];
            }
            
            // Remove the _method field from the data array
            unset($data['_method']);
            
            $result = $this->pageModel->updatePage($id, $data);
            
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Page updated successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to update page.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error updating page: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Deletes an existing page.
     * DELETE /api/admin/pages/{id}
     *
     * @param int $id
     */
    public function deletePage(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $page = $this->pageModel->findPageById($id);
            if (!$page) {
                $this->renderApiJson(['success' => false, 'message' => 'Page not found.'], 404);
                return;
            }
            
            $result = $this->pageModel->deletePage($id);
            
            if ($result) {
                // Delete the physical image file
                if ($page['featured_image'] && file_exists(ROOT_PATH . '/' . $page['featured_image'])) {
                    unlink(ROOT_PATH . '/' . $page['featured_image']);
                }
                $this->renderApiJson(['success' => true, 'message' => 'Page deleted successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to delete page.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error deleting page: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    // --- Menu Management Methods ---
    
    /**
     * Retrieves all menus for the admin panel.
     * GET /api/admin/menus
     */
    public function getMenus(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $menus = $this->menuModel->getAllMenus();
            foreach ($menus as &$menu) {
                $menu['items'] = json_decode($menu['items'], true);
            }
            $this->renderApiJson([
                'success' => true,
                'message' => 'Menus fetched successfully.',
                'data' => $menus
            ]);
        } catch (PDOException $e) {
            error_log("Database Error in getMenus: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in getMenus: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Creates a new menu.
     * POST /api/admin/menus
     */
    public function createMenu(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        $data = $this->getJsonData();
        $rules = [
            'name' => 'required|max:100',
            'location' => 'required|max:100|unique:menus',
            'items' => 'required|array',
            'is_active' => 'boolean'
        ];

        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['success' => false, 'message' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }
        
        try {
            $newMenuId = $this->menuModel->createMenu($data);

            if ($newMenuId) {
                $this->renderApiJson(['success' => true, 'message' => 'Menu created successfully.', 'id' => $newMenuId], 201);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to create menu.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error creating menu: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Updates an existing menu.
     * PUT /api/admin/menus/{id}
     *
     * @param int $id
     */
    public function updateMenu(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        $data = $this->getJsonData();
        $rules = [
            'name' => 'required|max:100',
            'location' => 'required|max:100|unique:menus,location,' . $id,
            'items' => 'required|array',
            'is_active' => 'boolean'
        ];

        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['success' => false, 'message' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }

        try {
            $result = $this->menuModel->updateMenu($id, $data);

            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Menu updated successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to update menu.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error updating menu: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Deletes an existing menu.
     * DELETE /api/admin/menus/{id}
     *
     * @param int $id
     */
    public function deleteMenu(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $result = $this->menuModel->deleteMenu($id);
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Menu deleted successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to delete menu.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error deleting menu: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }
}