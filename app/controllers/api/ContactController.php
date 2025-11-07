<?php
namespace App\Controllers\Api;
use App\Core\Controller;
use App\Services\ContactService;
use App\Services\AuthService;
class ContactController extends Controller
{
    private ContactService $contactService;
    public function __construct()
    {
        parent::__construct();
        $this->contactService = new ContactService();
    }
    
    /**
     * Handles the submission of the contact form.
     * Route: POST /api/contact/message
     */
    public function submit(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Invalid JSON input.'], 400);
            return;
        }
        $response = $this->contactService->submitMessage($data);
        $statusCode = $response['status'] === 'success' ? 200 : 400;
        $this->renderApiJson($response, $statusCode);
    }
}