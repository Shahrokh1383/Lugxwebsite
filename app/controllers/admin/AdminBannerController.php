<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Banner;
use App\Services\AuthService;
use App\Services\ValidationService;
use App\Services\UploadService;
use PDOException;
use Exception;

class AdminBannerController extends Controller
{
    private AuthService $authService;
    private Banner $bannerModel;
    private ValidationService $validator;
    private UploadService $uploadService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->bannerModel = new Banner();
        $this->validator = new ValidationService();
        $this->uploadService = new UploadService();
    }

    /**
     * Renders the static HTML view for managing banners.
     * GET /admin/banners
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
        
        $this->renderHtmlView('frontend/admin/admin_banners.html');
    }

    /**
     * Retrieves all banners for the admin panel.
     * GET /api/admin/banners
     */
    public function getBanners(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $banners = $this->bannerModel->getAllBanners();
            $this->renderApiJson([
                'success' => true,
                'message' => 'Banners fetched successfully.',
                'data' => $banners
            ]);
        } catch (PDOException $e) {
            error_log("Database Error in getBanners: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in getBanners: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Retrieves a single banner by ID.
     * GET /api/admin/banners/{id}
     *
     * @param int $id
     */
    public function getBanner(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $banner = $this->bannerModel->findBannerById($id);
            if ($banner) {
                $this->renderApiJson(['success' => true, 'message' => 'Banner found.', 'data' => $banner]);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Banner not found.'], 404);
            }
        } catch (PDOException $e) {
            error_log("Database Error in getBanner: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }

    /**
     * Creates a new banner.
     * POST /api/admin/banners
     */
    public function createBanner(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        $data = $_POST;
        $file = $_FILES['image'] ?? null;
        
        $rules = [
            'title' => 'required|max:255',
            'link' => 'url',
            'position' => 'required|in:homepage_slider,sidebar,header,footer'
        ];
        
        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['success' => false, 'message' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }
        
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->renderApiJson(['success' => false, 'message' => 'Image file is required.'], 400);
            return;
        }
        
        try {
            $uploadDir = 'public/uploads/banners/';
            $imagePath = $this->uploadService->uploadFile($file, $uploadDir);
            
            if ($imagePath === false) {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to upload image.'], 500);
                return;
            }
            
            $data['image'] = $imagePath;
            $data['is_active'] = isset($data['is_active']) ? 1 : 0;
            $data['sort_order'] = (int)($data['sort_order'] ?? 0);

            $newBannerId = $this->bannerModel->createBanner($data);
            
            if ($newBannerId) {
                $this->renderApiJson(['success' => true, 'message' => 'Banner created successfully.', 'id' => $newBannerId], 201);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to create banner.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error creating banner: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Updates an existing banner.
     * POST /api/admin/banners/{id} (using POST with _method=PUT for file uploads)
     *
     * @param int $id
     */
    public function updateBanner(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        $data = $_POST;
        $file = $_FILES['image'] ?? null;
        
        $rules = [
            'title' => 'required|max:255',
            'link' => 'url',
            'position' => 'required|in:homepage_slider,sidebar,header,footer'
        ];
        
        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['success' => false, 'message' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }

        try {
            $bannerToUpdate = $this->bannerModel->findBannerById($id);
            if (!$bannerToUpdate) {
                $this->renderApiJson(['success' => false, 'message' => 'Banner not found.'], 404);
                return;
            }
            
            // Handle image upload if a new file is provided
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                // Delete old image if it exists
                if ($bannerToUpdate['image'] && file_exists(ROOT_PATH . '/' . $bannerToUpdate['image'])) {
                    unlink(ROOT_PATH . '/' . $bannerToUpdate['image']);
                }
                
                $uploadDir = 'public/uploads/banners/';
                $imagePath = $this->uploadService->uploadFile($file, $uploadDir);
                
                if ($imagePath === false) {
                    $this->renderApiJson(['success' => false, 'message' => 'Failed to upload new image.'], 500);
                    return;
                }
                $data['image'] = $imagePath;
            } else {
                // Keep the old image path if no new file is uploaded
                $data['image'] = $bannerToUpdate['image'];
            }
            
            $data['is_active'] = isset($data['is_active']) ? 1 : 0;
            $data['sort_order'] = (int)($data['sort_order'] ?? 0);

            // Remove the _method field from the data array
            unset($data['_method']);

            $result = $this->bannerModel->updateBanner($id, $data);
            
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Banner updated successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to update banner.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error updating banner: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Deletes an existing banner.
     * DELETE /api/admin/banners/{id}
     *
     * @param int $id
     */
    public function deleteBanner(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $banner = $this->bannerModel->findBannerById($id);
            if (!$banner) {
                $this->renderApiJson(['success' => false, 'message' => 'Banner not found.'], 404);
                return;
            }
            
            $result = $this->bannerModel->deleteBanner($id);
            
            if ($result) {
                // Delete the physical image file
                if ($banner['image'] && file_exists(ROOT_PATH . '/' . $banner['image'])) {
                    unlink(ROOT_PATH . '/' . $banner['image']);
                }
                $this->renderApiJson(['success' => true, 'message' => 'Banner deleted successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to delete banner.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error deleting banner: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }
}