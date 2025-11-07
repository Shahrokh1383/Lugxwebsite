<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\ProductReview;
use App\Models\ReviewReply;
use App\Models\ReviewHelpfulness;
use App\Models\Product;
use App\Models\User;
use App\Services\ValidationService;
use App\Services\SecurityService;

class ReviewController extends Controller
{
    private ProductReview $reviewModel;
    private ReviewReply $replyModel;
    private ReviewHelpfulness $helpfulnessModel;
    private Product $productModel;
    private User $userModel;
    private ValidationService $validator;
    private SecurityService $security;

    public function __construct()
    {
        parent::__construct();
        $this->reviewModel = new ProductReview();
        $this->replyModel = new ReviewReply();
        $this->helpfulnessModel = new ReviewHelpfulness();
        $this->productModel = new Product();
        $this->userModel = new User();
        $this->validator = new ValidationService();
        $this->security = new SecurityService();
    }

    public function addReview(int $productId): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Authentication required.'], 401);
            return;
        }
        $userId = (int) $_SESSION['user_id'];

        $product = $this->productModel->find($productId);
        if (!$product) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Product not found.'], 404);
            return;
        }

        if (!$this->userModel->hasUserPurchasedProduct($userId, $productId)) {
            $this->renderApiJson(['status' => 'error', 'message' => 'You must purchase this product to review it.'], 403);
            return;
        }

        if ($this->reviewModel->hasUserReviewedProduct($userId, $productId)) {
            $this->renderApiJson(['status' => 'error', 'message' => 'You have already reviewed this product.'], 409);
            return;
        }

        $input = $this->getJsonData();
        $rules = [
            'rating' => 'required|int|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'review' => 'required|string|min:10|max:2000',
            'pros' => 'nullable|string|max:1000',
            'cons' => 'nullable|string|max:1000'
        ];

        if (!$this->validator->validate($input, $rules)) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 422);
            return;
        }

        $sanitized = [
            'product_id' => $productId,
            'user_id' => $userId,
            'rating' => (int) $input['rating'],
            'title' => $this->security->sanitizeString($input['title'] ?? ''),
            'review' => $this->security->sanitizeString($input['review']),
            'pros' => $this->security->sanitizeString($input['pros'] ?? ''),
            'cons' => $this->security->sanitizeString($input['cons'] ?? ''),
            'is_verified_purchase' => 1,
            'is_approved' => 0
        ];

        $reviewId = $this->reviewModel->createReview($sanitized);
        if (!$reviewId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Failed to create review.'], 500);
            return;
        }

        $this->renderApiJson(['status' => 'success', 'message' => 'Review submitted successfully and pending approval.', 'review_id' => $reviewId], 201);
    }

    public function getReviews(int $productId): void
    {
        $product = $this->productModel->find($productId);
        if (!$product) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Product not found.'], 404);
            return;
        }

        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
        $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
        $order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';

        $allowedSort = ['created_at', 'rating', 'helpful_count'];
        if (!in_array($sortBy, $allowedSort)) {
            $sortBy = 'created_at';
        }
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }

        $reviews = $this->reviewModel->findByProductId($productId, $page, $perPage, $sortBy, $order);
        $totalReviews = $this->reviewModel->countApprovedReviews($productId);

        foreach ($reviews as &$review) {
            $review['replies'] = $this->replyModel->getRepliesByReviewId($review['id']);
            $votes = $this->helpfulnessModel->countVotes($review['id']);
            $review['helpful'] = $votes['helpful'];
            $review['unhelpful'] = $votes['unhelpful'];
            
            if (isset($_SESSION['user_id'])) {
                $userVote = $this->helpfulnessModel->getUserVote($review['id'], (int) $_SESSION['user_id']);
                $review['user_helpful'] = $userVote ? (bool) $userVote['is_helpful'] : null;
            } else {
                $review['user_helpful'] = null;
            }
        }

        $response = [
            'status' => 'success',
            'data' => $reviews,
            'pagination' => [
                'total' => $totalReviews,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($totalReviews / $perPage)
            ]
        ];

        $this->renderApiJson($response);
    }

    public function addReply(int $reviewId): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Authentication required.'], 401);
            return;
        }
        $userId = (int) $_SESSION['user_id'];

        $review = $this->reviewModel->getReviewById($reviewId);
        if (!$review) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Review not found.'], 404);
            return;
        }

        $input = $this->getJsonData();
        $rules = [
            'reply' => 'required|string|min:1|max:1000'
        ];

        if (!$this->validator->validate($input, $rules)) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 422);
            return;
        }

        $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
        $isOwner = ($review['user_id'] == $userId);

        if (!$isAdmin && !$isOwner) {
            $this->renderApiJson(['status' => 'error', 'message' => 'You are not authorized to reply to this review.'], 403);
            return;
        }

        $sanitized = [
            'review_id' => $reviewId,
            'user_id' => $userId,
            'reply' => $this->security->sanitizeString($input['reply']),
            'is_admin_reply' => $isAdmin ? 1 : 0
        ];

        $replyId = $this->replyModel->createReply($sanitized);
        if (!$replyId) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Failed to create reply.'], 500);
            return;
        }

        $this->renderApiJson(['status' => 'success', 'message' => 'Reply added successfully.', 'reply_id' => $replyId], 201);
    }

    public function markHelpful(int $reviewId): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Authentication required.'], 401);
            return;
        }
        $userId = (int) $_SESSION['user_id'];

        $review = $this->reviewModel->getReviewById($reviewId);
        if (!$review) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Review not found.'], 404);
            return;
        }

        $input = $this->getJsonData();
        $rules = [
            'is_helpful' => 'required|boolean'
        ];

        if (!$this->validator->validate($input, $rules)) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 422);
            return;
        }

        $isHelpful = (bool) $input['is_helpful'];

        $existingVote = $this->helpfulnessModel->getUserVote($reviewId, $userId);
        if ($existingVote) {
            if ($existingVote['is_helpful'] == $isHelpful) {
                $this->helpfulnessModel->removeHelpfulness($reviewId, $userId);
                $action = 'removed';
            } else {
                $this->helpfulnessModel->removeHelpfulness($reviewId, $userId);
                $this->helpfulnessModel->addHelpfulness([
                    'review_id' => $reviewId,
                    'user_id' => $userId,
                    'is_helpful' => $isHelpful
                ]);
                $action = 'updated';
            }
        } else {
            $this->helpfulnessModel->addHelpfulness([
                'review_id' => $reviewId,
                'user_id' => $userId,
                'is_helpful' => $isHelpful
            ]);
            $action = 'added';
        }

        $votes = $this->helpfulnessModel->countVotes($reviewId);
        $newHelpfulCount = $votes['helpful'] - $votes['unhelpful'];
        $this->reviewModel->updateHelpfulCount($reviewId, $newHelpfulCount);

        $this->renderApiJson(['status' => 'success', 'message' => "Vote {$action} successfully."]);
    }

    public function getReviewStatus(int $productId): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Authentication required.'], 401);
            return;
        }
        $userId = (int) $_SESSION['user_id'];

        $hasPurchased = $this->userModel->hasUserPurchasedProduct($userId, $productId);
        $hasReviewed = $this->reviewModel->hasUserReviewedProduct($userId, $productId);

        $this->renderApiJson([
            'status' => 'success',
            'data' => [
                'has_purchased' => $hasPurchased,
                'has_reviewed' => $hasReviewed
            ]
        ]);
    }
}