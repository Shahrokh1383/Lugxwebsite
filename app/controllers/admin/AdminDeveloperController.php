<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Developer;
use App\Services\AuthService;
use App\Services\ValidationService;
use Exception;

class AdminDeveloperController extends Controller
{
    private Developer $developerModel;
    private AuthService $authService;
    private ValidationService $validator;

    public function __construct()
    {
        $this->developerModel = new Developer();
        $this->authService = new AuthService();
        $this->validator = new ValidationService();
    }
    
    //-------------------------------------------------------------
    // View Management
    //-------------------------------------------------------------

    /**
     * Renders the static HTML view for managing developers.
     * GET /admin/developers
     */
    public function index(): void
    {
        // This is a view handler. Authentication is handled by a middleware.
        $this->renderHtmlView('frontend/admin/admin_developers.html');
    }
    
    //-------------------------------------------------------------
    // API Endpoints
    // All API methods are protected by an authentication check.
    //-------------------------------------------------------------

    /**
     * Get a list of all developers.
     * GET /api/admin/developers
     */
    public function indexApi(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $developers = $this->developerModel->all();
            $this->renderApiJson($developers);
        } catch (Exception $e) {
            error_log("Error fetching developers: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Get details for a single developer.
     * GET /api/admin/developers/{id}
     * @param int $id
     */
    public function show(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $developer = $this->developerModel->findById($id);

            if (!$developer) {
                $this->renderApiJson(['error' => 'Developer not found.'], 404);
                return;
            }

            $this->renderApiJson($developer);
        } catch (Exception $e) {
            error_log("Error fetching developer: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Create a new developer.
     * POST /api/admin/developers
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
            'slug' => 'required|max:255|unique:developers',
            'description' => 'max:1000',
        ];

        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }

        try {
            $developerId = $this->developerModel->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
            ]);
            
            if ($developerId) {
                $this->renderApiJson(['message' => 'Developer created successfully!', 'developerId' => $developerId], 201);
            } else {
                $this->renderApiJson(['error' => 'Failed to create developer.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error creating developer: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Update an existing developer.
     * PUT /api/admin/developers/{id}
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
            'slug' => 'required|max:255|unique:developers,slug,' . $id,
            'description' => 'max:1000',
        ];

        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }

        try {
            $developerData = [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
            ];

            if ($this->developerModel->update($id, $developerData)) {
                $this->renderApiJson(['message' => 'Developer updated successfully!']);
            } else {
                $this->renderApiJson(['error' => 'Failed to update developer. It might not exist.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error updating developer: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Delete a developer.
     * DELETE /api/admin/developers/{id}
     * @param int $id
     */
    public function destroy(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            if ($this->developerModel->delete($id)) {
                $this->renderApiJson(['message' => 'Developer deleted successfully!']);
            } else {
                $this->renderApiJson(['error' => 'Failed to delete developer. It might not exist or has associated products.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error deleting developer: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Search for developers by name.
     * GET /api/admin/developers/search?query={term}
     */
    public function search(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'error' => 'Unauthorized access.'], 401);
            return;
        }

        $query = $_GET['query'] ?? '';

        if (empty($query)) {
            $this->renderApiJson(['success' => true, 'data' => []]);
            return;
        }

        try {
            $developers = $this->developerModel->searchByName($query);
            $this->renderApiJson(['success' => true, 'data' => $developers]);
        } catch (Exception $e) {
            error_log("Error searching developers: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'error' => 'An unexpected error occurred.'], 500);
        }
    }
}
