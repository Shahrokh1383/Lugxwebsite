<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Tag; // Make sure to use the Tag model

/**
* Tag API Controller
* Handles tag-related API requests.
*/
class TagController extends Controller
{
    private Tag $tagModel;

    public function __construct()
    {
        $this->tagModel = $this->model('Tag');

        if (!$this->tagModel) {
            error_log("Failed to load Tag model in TagController.");
            $this->renderApiJson(['message' => 'Internal server error: Tag model could not be loaded.'], 500);
            exit;
        }
    }

    
    /**
    * Get a list of tags.
    *
    * GET /api/tags
    * Example: /api/tags?limit=10
    */
    public function index(): void
    {
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);

        $tags = $this->tagModel->getAll($limit);

        if ($tags === false) {
            $this->renderApiJson(['message' => 'An error occurred while fetching tags.'], 500);
            return;
        }

        $this->renderApiJson([
            'status' => 'success',
            'total' => count($tags),
            'data' => $tags
        ]);
    }

    /**
    * Get a single tag by ID or slug.
    *
    * GET /api/tags/{id_or_slug}
    */
    public function show(string $idOrSlug): void
    {
        $tag = null;

        if (is_numeric($idOrSlug)) {
            $tag = $this->tagModel->findById((int)$idOrSlug);
        }else {
            $tag = $this->tagModel->findBySlug($idOrSlug);
        }

        // Tags now have 'is_active' column based on our recent update
        if (!$tag || !$tag['is_active']) {
            $this->renderApiJson(['message' => 'Tag not found or not active.'], 404);
            return;
        }
        
        $this->renderApiJson(['status' => 'success', 'data' => $tag]);
    }
}
