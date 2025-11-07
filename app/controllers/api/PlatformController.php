<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Platform;

/**
* Platform API Controller
* Handles platform-related API requests.
*/
class PlatformController extends Controller
{
    private Platform $platformModel;

    public function __construct()
    {
        $this->platformModel = $this->model('Platform');

        if (!$this->platformModel) {
            error_log("Failed to load Platform model in PlatformController.");
            $this->renderApiJson(['message' => 'Internal server error: Platform model could not be loaded.'], 500);
            exit;
        }
    }

    /**
    * Get a list of platforms.
    *
    * GET /api/platforms
    * Example: /api/platforms?limit=5
    */
    public function index(): void 
    {
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);

        $platforms = $this->platformModel->getAll($limit);

        if ($platforms === false) {
            $this->renderApiJson(['message' => 'An error occurred while fetching platforms.'], 500);
            return;
        }

        $this->renderApiJson([
            'status' => 'success',
            'total' => count($platforms),
            'data' => $platforms
        ]);
    }

    /**
    * Get a single platform by ID or slug.
    *
    * GET /api/platforms/{id_or_slug}
    */
    public function show(string $idOrSlug): void
    {
        $platform = null;

        if (is_numeric($idOrSlug)) {
            $platform = $this->platformModel->findById((int)$idOrSlug);
        }else {
            $platform = $this->platformModel->findBySlug($idOrSlug);
        }

        if (!$platform || !$platform['is_active']) {
            $this->renderApiJson(['message' => 'Platform not found or not active.'], 404);
            return;
        }
        
        $this->renderApiJson(['status' => 'success', 'data' => $platform]);
    }
}
