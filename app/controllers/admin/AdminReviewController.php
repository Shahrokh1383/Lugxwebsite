<?php
namespace App\Controllers\Admin;
use App\Core\Controller;
use App\Services\AuthService;
use App\Models\ProductReview;
use App\Models\ReviewReply;
use App\Services\ValidationService;
use PDOException;
use Exception;
class AdminReviewController extends Controller
{
    private AuthService $authService;
    private ProductReview $reviewModel;
    private ReviewReply $replyModel;
    private ValidationService $validator;
    
    public function __construct()
    {
        $this->authService = new AuthService();
        $this->reviewModel = $this->model('ProductReview');
        $this->replyModel = $this->model('ReviewReply');
        $this->validator = new ValidationService();
        
        if (!$this->reviewModel || !$this->replyModel) {
            error_log("ERROR: AdminReviewController - Failed to load required models.");
        }
    }
    
    /**
     * Renders the static HTML view for managing product reviews.
     * GET /admin/reviews
     */
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->redirect('/admin/login');
            return;
        }
        
        $this->renderHtmlView('frontend/admin/admin_reviews.html');
    }
    
    /**
     * Retrieves all product reviews with associated product and user details.
     * GET /api/admin/reviews
     */
    public function indexApi(): void
    {
        $this->validateAdminAccess();
        
        try {
            // Get pagination and filter parameters
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
            $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
            $order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
            
            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 10;
            
            // Build filters array
            $filters = [];
            if (isset($_GET['status']) && in_array($_GET['status'], ['approved', 'pending'])) {
                $filters['status'] = $_GET['status'];
            }
            if (isset($_GET['rating']) && $_GET['rating'] >= 1 && $_GET['rating'] <= 5) {
                $filters['rating'] = (int)$_GET['rating'];
            }
            if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
                $filters['search'] = trim($_GET['search']);
            }
            
            $reviews = $this->reviewModel->getAllWithUserInfoAndProductName($page, $perPage, $filters, $sortBy, $order);
            $totalReviews = $this->reviewModel->countAllReviews($filters);
            
            $this->renderApiJson([
                'success' => true,
                'message' => 'Product reviews fetched successfully.',
                'data' => $reviews,
                'pagination' => [
                    'total' => $totalReviews,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => ceil($totalReviews / $perPage)
                ]
            ]);
        } catch (PDOException $e) {
            error_log("Database Error in indexApi: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in indexApi: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Get review statistics for admin dashboard
     * GET /api/admin/reviews/statistics
     */
    public function statistics(): void
    {
        $this->validateAdminAccess();
        
        try {
            $statistics = $this->reviewModel->getReviewStatistics();
            $this->renderApiJson([
                'success' => true,
                'message' => 'Review statistics fetched successfully.',
                'data' => $statistics
            ]);
        } catch (PDOException $e) {
            error_log("Database Error in statistics: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in statistics: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Get a specific review by ID
     * GET /api/admin/reviews/{id}
     */
    public function show(int $id): void
    {
        $this->validateAdminAccess();
        
        try {
            $review = $this->reviewModel->getReviewById($id);
            if ($review) {
                $this->renderApiJson([
                    'success' => true,
                    'message' => 'Review fetched successfully.',
                    'data' => $review
                ]);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Review not found.'], 404);
            }
        } catch (PDOException $e) {
            error_log("Database Error in show: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }
    
    /**
     * Approves a specific product review.
     * POST /api/admin/reviews/{id}/approve
     */
    public function approve(int $id): void
    {
        $this->validateAdminAccess();
        
        try {
            $result = $this->reviewModel->updateStatus($id, true);
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Review approved successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to approve review. Review not found.'], 404);
            }
        } catch (PDOException $e) {
            error_log("Database Error in approve: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }
    
    /**
     * Disapproves (rejects) a specific product review.
     * POST /api/admin/reviews/{id}/reject
     */
    public function reject(int $id): void
    {
        $this->validateAdminAccess();
        
        try {
            $result = $this->reviewModel->updateStatus($id, false);
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Review rejected successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to reject review. Review not found.'], 404);
            }
        } catch (PDOException $e) {
            error_log("Database Error in reject: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }
    
    /**
     * Deletes a specific product review.
     * DELETE /api/admin/reviews/{id}
     */
    public function destroy(int $id): void
    {
        $this->validateAdminAccess();
        
        try {
            $result = $this->reviewModel->delete($id);
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Review deleted successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to delete review. Review not found.'], 404);
            }
        } catch (PDOException $e) {
            error_log("Database Error in destroy: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }
    
    /**
     * Adds or updates an admin reply to a specific review.
     * POST /api/admin/reviews/{id}/reply
     */
    public function reply(int $id): void
    {
        $this->validateAdminAccess();
        
        $data = $this->getJsonData();
        $reply = $data['reply'] ?? null;
        
        if ($reply === null || trim($reply) === '') {
            $this->renderApiJson(['success' => false, 'message' => 'Reply text is required.'], 400);
            return;
        }
        
        try {
            // Get current admin user
            $currentUser = $this->authService->getCurrentUser();
            if (!$currentUser) {
                $this->renderApiJson(['success' => false, 'message' => 'User not authenticated.'], 401);
                return;
            }
            
            // Check if review exists
            $review = $this->reviewModel->getReviewById($id);
            if (!$review) {
                $this->renderApiJson(['success' => false, 'message' => 'Review not found.'], 404);
                return;
            }
            
            // Check if admin already replied to this review
            $adminReply = $this->replyModel->getAdminReplyByReviewId($id);
            
            // If admin already replied, update the reply
            if ($adminReply) {
                $updateResult = $this->replyModel->updateReply($adminReply['id'], $reply);
                if ($updateResult) {
                    $this->renderApiJson(['success' => true, 'message' => 'Reply updated successfully.']);
                } else {
                    $this->renderApiJson(['success' => false, 'message' => 'Failed to update reply.'], 500);
                }
            } else {
                // Create new admin reply
                $replyData = [
                    'review_id' => $id,
                    'user_id' => $currentUser['id'],
                    'reply' => $reply,
                    'is_admin_reply' => true
                ];
                
                $result = $this->replyModel->createReply($replyData);
                if ($result) {
                    $this->renderApiJson(['success' => true, 'message' => 'Reply added successfully.']);
                } else {
                    $this->renderApiJson(['success' => false, 'message' => 'Failed to add reply.'], 500);
                }
            }
        } catch (PDOException $e) {
            error_log("Database Error in reply: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }
    
    /**
    * Get replies for a specific review
    * GET /api/admin/reviews/{id}/replies
    */
    public function getReplies(int $id): void
    {
        $this->validateAdminAccess();
        
        try {
            $replies = $this->replyModel->getRepliesByReviewId($id);
            $this->renderApiJson([
                'success' => true,
                'message' => 'Replies fetched successfully.',
                'data' => $replies
            ]);
        } catch (PDOException $e) {
            error_log("Database Error in getReplies: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }
    
    /**
     * Validate admin access and return error if unauthorized
     */
    private function validateAdminAccess(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            exit;
        }
    }
}