<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Publisher;
use App\Services\AuthService;
use App\Services\ValidationService;
use Exception;

class AdminPublisherController extends Controller
{
    private Publisher $publisherModel;
    private AuthService $authService;
    private ValidationService $validator;

    public function __construct()
    {
        $this->publisherModel = new Publisher();
        $this->authService = new AuthService();
        $this->validator = new ValidationService();
    }
    
    //-------------------------------------------------------------
    // View Management
    //-------------------------------------------------------------

    /**
     * Renders the static HTML view for managing publishers.
     * GET /admin/publishers
     */
    public function index(): void
    {
        // This is a view handler. Authentication is handled by a middleware.
        $this->renderHtmlView('frontend/admin/admin_publishers.html');
    }
    
    //-------------------------------------------------------------
    // API Endpoints
    // All API methods are protected by an authentication check.
    //-------------------------------------------------------------

    /**
     * Get a list of all publishers.
     * GET /api/admin/publishers
     */
    public function indexApi(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $publishers = $this->publisherModel->all();
            $this->renderApiJson($publishers);
        } catch (Exception $e) {
            error_log("Error fetching publishers: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Get details for a single publisher.
     * GET /api/admin/publishers/{id}
     * @param int $id
     */
    public function show(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $publisher = $this->publisherModel->findById($id);

            if (!$publisher) {
                $this->renderApiJson(['error' => 'Publisher not found.'], 404);
                return;
            }

            $this->renderApiJson($publisher);
        } catch (Exception $e) {
            error_log("Error fetching publisher: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Create a new publisher.
     * POST /api/admin/publishers
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
            'slug' => 'required|max:255|unique:publishers',
            'description' => 'max:1000',
        ];

        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }

        try {
            $publisherId = $this->publisherModel->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
            ]);
            
            if ($publisherId) {
                $this->renderApiJson(['message' => 'Publisher created successfully!', 'publisherId' => $publisherId], 201);
            } else {
                $this->renderApiJson(['error' => 'Failed to create publisher.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error creating publisher: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Update an existing publisher.
     * PUT /api/admin/publishers/{id}
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
            'slug' => 'required|max:255|unique:publishers,slug,' . $id,
            'description' => 'max:1000',
        ];

        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }

        try {
            $publisherData = [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
            ];
            
            if ($this->publisherModel->update($id, $publisherData)) {
                $this->renderApiJson(['message' => 'Publisher updated successfully!']);
            } else {
                $this->renderApiJson(['error' => 'Failed to update publisher. It might not exist.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error updating publisher: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Delete a publisher.
     * DELETE /api/admin/publishers/{id}
     * @param int $id
     */
    public function destroy(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            if ($this->publisherModel->delete($id)) {
                $this->renderApiJson(['message' => 'Publisher deleted successfully!']);
            } else {
                $this->renderApiJson(['error' => 'Failed to delete publisher. It might not exist or has associated products.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error deleting publisher: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Search for publishers by name.
     * GET /api/admin/publishers/search?query={term}
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
            $publishers = $this->publisherModel->searchByName($query);
            $this->renderApiJson(['success' => true, 'data' => $publishers]);
        } catch (Exception $e) {
            error_log("Error searching publishers: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'error' => 'An unexpected error occurred.'], 500);
        }
    }
}
