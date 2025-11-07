<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Developer;

/**
* Developer API Controller
* Handles developer-related API requests.
*/
class DeveloperController extends Controller
{
    private Developer $developerModel;

    public function __construct()
    {
        $this->developerModel = $this->model('Developer');

        if (!$this->developerModel) {
            error_log("Failed to load Developer model in DeveloperController.");
            $this->renderApiJson(['message' => 'Internal server error: Developer model could not be loaded.'], 500);
            exit;
        }
    }

    /**
    * Get a list of developers.
    *
    * GET /api/developers
    * Example: /api/developers?limit=5
    */
    public function index(): void
    {
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);

        $developers = $this->developerModel->getAll($limit);

        if ($developers === false) {
            $this->renderApiJson(['message' => 'An error occurred while fetching developers.'], 500);
            return;
        }

        $this->renderApiJson([
            'status' => 'success',
            'total' => count($developers),
            'data' => $developers
        ]);
    }

    
    /**
    * Get a single developer by ID or slug.
    *
    * GET /api/developers/{id_or_slug}
    */
    public function show(string $idOrSlug): void
    {
        $developer = null;

        if (is_numeric($idOrSlug)) {
            $developer = $this->developerModel->findById((int)$idOrSlug);
        }else {
            $developer = $this->developerModel->findBySlug($idOrSlug);
        }

        if (!$developer || !$developer['is_active']) {
            $this->renderApiJson(['message' => 'Developer not found or not active.'], 404);
            return;
        }
        
        $this->renderApiJson(['status' => 'success', 'data' => $developer]);
    }
}
