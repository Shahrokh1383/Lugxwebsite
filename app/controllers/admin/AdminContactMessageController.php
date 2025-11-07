<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\ContactMessage;
use App\Services\AuthService;
use PDOException;
use Exception;

class AdminContactMessageController extends Controller
{
    private AuthService $authService;
    private ContactMessage $contactMessageModel;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->contactMessageModel = new ContactMessage();

        if (!$this->contactMessageModel) {
            error_log("ERROR: AdminContactMessageController - Failed to load ContactMessage model.");
        }
    }

    /**
     * Renders the static HTML view for managing contact messages.
     * GET /admin/messages
     */
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('Admin')) {
            $this->redirect('/admin/login');
            return;
        }

        $this->renderHtmlView('frontend/admin/admin_messages.html');
    }

    /**
     * Retrieves all contact messages for the admin panel.
     * This method acts as an API endpoint.
     * GET /api/admin/messages
     */
    public function getMessages(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('Admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $messages = $this->contactMessageModel->getAll();
            $this->renderApiJson([
                'success' => true,
                'message' => 'Contact messages fetched successfully.',
                'data' => $messages
            ]);
        }catch (PDOException $e) {
            error_log("Database Error in getMessages: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }catch (Exception $e) {
            error_log("General Error in getMessages: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Marks a specific contact message as read (status = 'in_progress').
     * POST /api/admin/messages/{id}/mark-read
     *
     * @param int $id The ID of the message to update.
     */
    public function markAsRead(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            // We use 'in_progress' to signify that an admin has viewed it.
            $result = $this->contactMessageModel->updateStatus($id, 'in_progress');
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Message marked as read successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to mark message as read. Message not found.'], 404);
            }
        }catch (PDOException $e) {
            error_log("Database Error in markAsRead: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }

    /**
     * Deletes a specific contact message.
     * DELETE /api/admin/messages/{id}
     *
     * @param int $id The ID of the message to delete.
     */
    public function deleteMessage(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $result = $this->contactMessageModel->delete($id);
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Message deleted successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to delete message. Message not found.'], 404);
            }
        }catch (PDOException $e) {
            error_log("Database Error in deleteMessage: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }
}