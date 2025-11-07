<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Tag;
use App\Services\AuthService;
use App\Services\ValidationService;
use Exception;

class AdminTagController extends Controller
{
    private Tag $tagModel;
    private AuthService $authService;
    private ValidationService $validator;

    public function __construct()
    {
        $this->tagModel = new Tag();
        $this->authService = new AuthService();
        $this->validator = new ValidationService();
    }

    //-------------------------------------------------------------
    // View Management
    //-------------------------------------------------------------
    
    /**
     * Renders the static HTML view for managing tags.
     * GET /admin/tags
     */
    public function index(): void
    {
        // This is a view handler. Authentication is handled by a middleware.
        $this->renderHtmlView('frontend/admin/admin_tags.html');
    }

    //-------------------------------------------------------------
    // API Endpoints
    // All API methods are protected by an authentication check.
    //-------------------------------------------------------------

    /**
     * Get a list of all tags.
     * GET /api/admin/tags
     */
    public function indexApi(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $tags = $this->tagModel->all();
            $this->renderApiJson($tags);
        } catch (Exception $e) {
            error_log("Error fetching tags: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Get details for a single tag.
     * GET /api/admin/tags/{id}
     * @param int $id
     */
    public function show(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $tag = $this->tagModel->findById($id);

            if (!$tag) {
                $this->renderApiJson(['error' => 'Tag not found.'], 404);
                return;
            }

            $this->renderApiJson($tag);
        } catch (Exception $e) {
            error_log("Error fetching tag: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Create a new tag.
     * POST /api/admin/tags
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
            'slug' => 'required|max:255|unique:tags',
            'description' => 'max:1000',
        ];

        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }

        try {
            $tagId = $this->tagModel->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
            ]);
            
            if ($tagId) {
                $this->renderApiJson(['message' => 'Tag created successfully!', 'tagId' => $tagId], 201);
            } else {
                $this->renderApiJson(['error' => 'Failed to create tag.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error creating tag: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Update an existing tag.
     * PUT /api/admin/tags/{id}
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
            'slug' => 'required|max:255|unique:tags,slug,' . $id,
            'description' => 'max:1000',
        ];

        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }

        try {
            $tagData = [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
            ];

            if ($this->tagModel->update($id, $tagData)) {
                $this->renderApiJson(['message' => 'Tag updated successfully!']);
            } else {
                $this->renderApiJson(['error' => 'Failed to update tag. It might not exist.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error updating tag: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Delete a tag.
     * DELETE /api/admin/tags/{id}
     * @param int $id
     */
    public function destroy(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            if ($this->tagModel->delete($id)) {
                $this->renderApiJson(['message' => 'Tag deleted successfully!']);
            } else {
                $this->renderApiJson(['error' => 'Failed to delete tag. It might not exist or has associated products.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error deleting tag: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }
}
