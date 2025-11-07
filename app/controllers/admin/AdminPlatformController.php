<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Platform;
use App\Services\AuthService;
use App\Services\ValidationService;
use Exception;

class AdminPlatformController extends Controller
{
    private Platform $platformModel;
    private AuthService $authService;
    private ValidationService $validator;

    public function __construct()
    {
        $this->platformModel = new Platform();
        $this->authService = new AuthService();
        $this->validator = new ValidationService();
    }
    
    //-------------------------------------------------------------
    // View Management
    //-------------------------------------------------------------

    /**
     * Renders the static HTML view for managing platforms.
     * GET /admin/platforms
     */
    public function index(): void
    {
        // This is a view handler. Authentication is handled by a middleware.
        $this->renderHtmlView('frontend/admin/admin_platforms.html');
    }

    //-------------------------------------------------------------
    // API Endpoints
    // All API methods are protected by an authentication check.
    //-------------------------------------------------------------

    /**
     * Get a list of all platforms.
     * GET /api/admin/platforms
     */
    public function indexApi(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $platforms = $this->platformModel->all();
            $this->renderApiJson($platforms);
        } catch (Exception $e) {
            error_log("Error fetching platforms: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Get details for a single platform.
     * GET /api/admin/platforms/{id}
     * @param int $id
     */
    public function show(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $platform = $this->platformModel->findById($id);

            if (!$platform) {
                $this->renderApiJson(['error' => 'Platform not found.'], 404);
                return;
            }

            $this->renderApiJson($platform);
        } catch (Exception $e) {
            error_log("Error fetching platform: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Create a new platform.
     * POST /api/admin/platforms
     */
    public function store(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        $data = $this->getJsonData();

        // Validation rules
        $rules = [
            'name' => 'required|max:255',
            'slug' => 'required|max:255|unique:platforms',
            'icon' => 'max:255',
        ];

        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }

        try {
            $platformId = $this->platformModel->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'icon' => $data['icon'] ?? null,
            ]);
            
            if ($platformId) {
                $this->renderApiJson(['message' => 'Platform created successfully!', 'platformId' => $platformId], 201);
            } else {
                $this->renderApiJson(['error' => 'Failed to create platform.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error creating platform: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Update an existing platform.
     * PUT /api/admin/platforms/{id}
     * @param int $id
     */
    public function update(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        $data = $this->getJsonData();
        
        // Validation rules
        $rules = [
            'name' => 'required|max:255',
            'slug' => 'required|max:255|unique:platforms,slug,' . $id,
            'icon' => 'max:255',
        ];

        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }

        try {
            $platformData = [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'icon' => $data['icon'] ?? null,
            ];
            
            if ($this->platformModel->update($id, $platformData)) {
                $this->renderApiJson(['message' => 'Platform updated successfully!']);
            } else {
                $this->renderApiJson(['error' => 'Failed to update platform. It might not exist.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error updating platform: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Delete a platform.
     * DELETE /api/admin/platforms/{id}
     * @param int $id
     */
    public function destroy(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            if ($this->platformModel->delete($id)) {
                $this->renderApiJson(['message' => 'Platform deleted successfully!']);
            } else {
                $this->renderApiJson(['error' => 'Failed to delete platform. It might not exist or has associated products.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error deleting platform: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }
}
