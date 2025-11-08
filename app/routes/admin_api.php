<?php
use App\Core\Router;
use App\Controllers\Admin\AdminAuthController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AdminUserController;
use App\Controllers\Admin\AdminProductController;
use App\Controllers\Admin\AdminCategoryController;
use App\Controllers\Admin\AdminPlatformController;
use App\Controllers\Admin\AdminPublisherController;
use App\Controllers\Admin\AdminDeveloperController;
use App\Controllers\Admin\AdminTagController;
use App\Controllers\Admin\AdminOrderController;
use App\Controllers\Admin\AdminCouponController;
use App\Controllers\Admin\AdminReviewController;
use App\Controllers\Admin\AdminContactMessageController;
use App\Controllers\Admin\AdminNewsletterController;
use App\Controllers\Admin\AdminSettingsController;
use App\Controllers\Admin\AdminReportController;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\CsrfMiddleware;

// --- Admin Authentication Routes ---
// These routes are public for the admin panel and do not require AdminMiddleware
$router->post('/api/admin/auth/login', [AdminAuthController::class, 'login'], [CsrfMiddleware::class]);

// All following routes require authentication (AdminMiddleware)
// The CsrfMiddleware is applied globally to this group
$router->group('/api/admin', function(Router $router) {
    // Admin Auth Routes
    $router->post('/auth/logout', [AdminAuthController::class, 'logout']);
    
    // Admin Dashboard Routes
    $router->get('/dashboard/stats', [AdminDashboardController::class, 'getDashboardStats']);
    
    // Admin User Management Routes (CRUD)
    $router->get('/users', [AdminUserController::class, 'getUsers']);
    $router->get('/users/{id}', [AdminUserController::class, 'getUser']);
    $router->post('/users', [AdminUserController::class, 'createUser']);
    $router->put('/users/{id}', [AdminUserController::class, 'updateUser']);
    $router->delete('/users/{id}', [AdminUserController::class, 'deleteUser']);
    
    // Route for fetching user roles
    $router->get('/roles', [AdminUserController::class, 'getRoles']);
    
    // --- Admin Product Management Routes (CRUD) ---
    $router->get('/products', [AdminProductController::class, 'indexApi']);
    $router->get('/products/form-data', [AdminProductController::class, 'getFormData']); // Added this route
    $router->get('/products/{id}', [AdminProductController::class, 'show']);
    $router->post('/products', [AdminProductController::class, 'store']);
    $router->put('/products/{id}', [AdminProductController::class, 'update']);
    $router->post('/products/{id}', [AdminProductController::class, 'update']);
    $router->delete('/products/{id}', [AdminProductController::class, 'destroy']);
    
   // --- Admin Category Management Routes (CRUD) ---
    $router->get('/categories', [AdminCategoryController::class, 'indexApi']);
    $router->get('/categories/top-level', [AdminCategoryController::class, 'getTopLevel']);
    $router->get('/categories/{id}', [AdminCategoryController::class, 'show']);
    $router->post('/categories', [AdminCategoryController::class, 'store'], ['enctype' => 'multipart/form-data']);
    $router->put('/categories/{id}', [AdminCategoryController::class, 'update'], ['enctype' => 'multipart/form-data']);
    $router->post('/categories/{id}', [AdminCategoryController::class, 'update'], ['enctype' => 'multipart/form-data']);
    $router->delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);
    
    // --- Admin Platform Management Routes (CRUD) ---
    $router->get('/platforms', [AdminPlatformController::class, 'indexApi']);
    $router->get('/platforms/{id}', [AdminPlatformController::class, 'show']);
    $router->post('/platforms', [AdminPlatformController::class, 'store']);
    $router->put('/platforms/{id}', [AdminPlatformController::class, 'update']);
    $router->post('/platforms/{id}', [AdminPlatformController::class, 'update']);
    $router->delete('/platforms/{id}', [AdminPlatformController::class, 'destroy']);
    
    // --- Admin Publisher Management Routes (CRUD) ---
    $router->get('/publishers/search', [AdminPublisherController::class, 'search']);
    $router->get('/publishers', [AdminPublisherController::class, 'indexApi']);
    $router->get('/publishers/{id}', [AdminPublisherController::class, 'show']);
    $router->post('/publishers', [AdminPublisherController::class, 'store']);
    $router->put('/publishers/{id}', [AdminPublisherController::class, 'update']);
    $router->post('/publishers/{id}', [AdminPublisherController::class, 'update']);
    $router->delete('/publishers/{id}', [AdminPublisherController::class, 'destroy']);
    
    // --- Admin Developer Management Routes (CRUD) ---
    $router->get('/developers/search', [AdminDeveloperController::class, 'search']);
    $router->get('/developers', [AdminDeveloperController::class, 'indexApi']);
    $router->get('/developers/{id}', [AdminDeveloperController::class, 'show']);
    $router->post('/developers', [AdminDeveloperController::class, 'store']);
    $router->put('/developers/{id}', [AdminDeveloperController::class, 'update']);
    $router->post('/developers/{id}', [AdminDeveloperController::class, 'update']);
    $router->delete('/developers/{id}', [AdminDeveloperController::class, 'destroy']);
    
    // --- Admin Tag Management Routes (CRUD) ---
    $router->get('/tags', [AdminTagController::class, 'indexApi']);
    $router->get('/tags/{id}', [AdminTagController::class, 'show']);
    $router->post('/tags', [AdminTagController::class, 'store']);
    $router->put('/tags/{id}', [AdminTagController::class, 'update']);
    $router->post('/tags/{id}', [AdminTagController::class, 'update']);
    $router->delete('/tags/{id}', [AdminTagController::class, 'destroy']);
    
    // --- Admin Order Management Routes ---
    $router->get('/orders', [AdminOrderController::class, 'indexApi']);
    $router->get('/orders/{id}', [AdminOrderController::class, 'show']);
    $router->put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    $router->put('/orders/{id}/payment-status', [AdminOrderController::class, 'updatePaymentStatus']);
    $router->post('/orders/item/{itemId}/key', [AdminOrderController::class, 'assignKey']);
    $router->get('/orders/{id}/status-history', [AdminOrderController::class, 'getStatusHistory']);
    
    // --- Admin Coupon Management Routes (CRUD) ---
    $router->get('/coupons', [AdminCouponController::class, 'indexApi']);
    $router->get('/coupons/{id}', [AdminCouponController::class, 'show']);
    $router->post('/coupons', [AdminCouponController::class, 'store']);
    $router->put('/coupons/{id}', [AdminCouponController::class, 'update']);
    $router->post('/coupons/{id}', [AdminCouponController::class, 'update']);
    $router->delete('/coupons/{id}', [AdminCouponController::class, 'destroy']);
    
    // --- Admin Review Management Routes ---
    $router->get('/reviews', [AdminReviewController::class, 'indexApi']);
    $router->get('/reviews/statistics', [AdminReviewController::class, 'statistics']);
    $router->get('/reviews/{id}', [AdminReviewController::class, 'show']);
    $router->get('/reviews/{id}/replies', [AdminReviewController::class, 'getReplies']);
    $router->post('/reviews/{id}/approve', [AdminReviewController::class, 'approve']);
    $router->post('/reviews/{id}/reject', [AdminReviewController::class, 'reject']);
    $router->post('/reviews/{id}/reply', [AdminReviewController::class, 'reply']);
    $router->delete('/reviews/{id}', [AdminReviewController::class, 'destroy']);
    
    // --- Admin Contact Messages Routes ---
    $router->get('/messages', [AdminContactMessageController::class, 'index']); // Renders the HTML page
    $router->get('/messages/data', [AdminContactMessageController::class, 'getMessages']); // Fetches message data via AJAX
    $router->post('/messages/{id}/mark-read', [AdminContactMessageController::class, 'markAsRead']);
    $router->delete('/messages/{id}', [AdminContactMessageController::class, 'deleteMessage']);
    
    // --- Admin Newsletter Routes ---
     $router->get('/newsletter', [AdminNewsletterController::class, 'index']); // Renders the HTML page
    
    // Newsletter Subscribers Routes
    $router->get('/newsletter/subscribers', [AdminNewsletterController::class, 'getSubscribers']); // Fetches subscriber data via AJAX
    $router->delete('/newsletter/subscribers/{id}', [AdminNewsletterController::class, 'deleteSubscriber']);
    
    // Newsletter Campaigns Routes
    $router->get('/newsletter/campaigns', [AdminNewsletterController::class, 'getCampaigns']); // Fetches campaigns data via AJAX
    $router->get('/newsletter/campaigns/{id}', [AdminNewsletterController::class, 'getCampaign']); // Get a specific campaign
    $router->post('/newsletter/campaigns', [AdminNewsletterController::class, 'createCampaign']); // Create a new campaign
    $router->put('/newsletter/campaigns/{id}', [AdminNewsletterController::class, 'updateCampaign']); // Update a campaign
    $router->delete('/newsletter/campaigns/{id}', [AdminNewsletterController::class, 'deleteCampaign']); // Delete a campaign
    $router->post('/newsletter/campaigns/{id}/schedule', [AdminNewsletterController::class, 'scheduleCampaign']); // Schedule a campaign
    $router->post('/newsletter/campaigns/{id}/send', [AdminNewsletterController::class, 'sendCampaign']); // Send a campaign immediately
    // Legacy Routes (for backward compatibility)
    $router->get('/newsletter/data', [AdminNewsletterController::class, 'getSubscribers']); // Fetches subscriber data via AJAX
    $router->delete('/newsletter/{id}', [AdminNewsletterController::class, 'deleteSubscriber']);
    $router->post('/newsletter/send-email', [AdminNewsletterController::class, 'sendGroupEmail']);
    // Newsletter Statistics
    $router->get('/newsletter/stats', [AdminNewsletterController::class, 'getStats']); // Get newsletter statistics
    // --- Admin Settings Routes ---
    $router->get('/settings', [AdminSettingsController::class, 'getSettings']);
    $router->put('/settings', [AdminSettingsController::class, 'updateSettings']);
    // --- Admin Reports Routes ---
    $router->get('/reports/sales', [AdminReportController::class, 'getSalesReports']);
    $router->get('/reports/top-products', [AdminReportController::class, 'getTopProducts']);
    $router->get('/reports/user-activity', [AdminReportController::class, 'getUserActivity']);
    $router->get('/reports/page-views', [AdminReportController::class, 'getPageViews']);
    $router->get('/reports/dashboard-stats', [AdminReportController::class, 'getDashboardStats']);
}, [AdminMiddleware::class]);