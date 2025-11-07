<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Publisher;

/**
* Publisher API Controller
* Handles publisher-related API requests.
*/
class PublisherController extends Controller
{
    private Publisher $publisherModel;

    public function __construct()
    {
        $this->publisherModel = $this->model('Publisher');

        if (!$this->publisherModel) {
            error_log("Failed to load Publisher model in PublisherController.");
            $this->renderApiJson(['message' => 'Internal server error: Publisher model could not be loaded.'], 500);
            exit;
        }
    }

    /**
    * Get a list of publishers.
    *
    * GET /api/publishers
    * Example: /api/publishers?limit=5
    */
    public function index(): void
    {
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);

        $publishers = $this->publisherModel->getAll($limit);

        if ($publishers === false) {
            $this->renderApiJson(['message' => 'An error occurred while fetching publishers.'], 500);
            return;
        }

        $this->renderApiJson([
            'status' => 'success',
            'total' => count($publishers),
            'data' => $publishers
        ]);
    }

    /**
    * Get a single publisher by ID or slug.
    *
    * GET /api/publishers/{id_or_slug}
    */
    public function show(string $idOrSlug): void
    {
        $publisher = null;

        if (is_numeric($idOrSlug)) {
            $publisher = $this->publisherModel->findById((int)$idOrSlug);
        }else {
            $publisher = $this->publisherModel->findBySlug($idOrSlug);
        }

        if (!$publisher || !$publisher['is_active']) {
            $this->renderApiJson(['message' => 'Publisher not found or not active.'], 404);
            return;
        }
        
        $this->renderApiJson(['status' => 'success', 'data' => $publisher]);
    }
}
