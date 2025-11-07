<?php
// app/routes/api.php
// Defines API routes for regular users.
use App\Controllers\Api\AuthController;
use App\Controllers\Api\UserController;
use App\Controllers\Api\ProductController;
use App\Controllers\Api\CategoryController;
use App\Controllers\Api\PlatformController;
use App\Controllers\Api\PublisherController;
use App\Controllers\Api\DeveloperController;
use App\Controllers\Api\TagController;
use App\Controllers\Api\FeatureController;
use App\Controllers\Api\CartController;
use App\Controllers\Api\WishlistController;
use App\Controllers\Api\OrderController;
use App\Controllers\Api\ReviewController;
use App\Controllers\Api\NewsletterController;
use App\Controllers\Api\ContactController;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\GuestMiddleware;
use App\Core\Router;

// Apply CsrfMiddleware globally to all POST, PUT, DELETE API requests under /api prefix.
// This ensures all modifying API requests are CSRF protected by default.
$router->middleware(CsrfMiddleware::class, '/api', ['POST', 'PUT', 'DELETE']);

// Group routes that require a guest (not logged in) user.
$router->group('/api', function(Router $router) {
    $router->post('/register', [AuthController::class, 'register']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
    $router->post('/reset-password', [AuthController::class, 'resetPassword']);
    // Frontend login/register routes are served directly, not via API.
}, [GuestMiddleware::class]); // Apply GuestMiddleware to these routes

// Public API routes (no authentication required)
$router->get('/api/verify-email', [AuthController::class, 'verifyEmail']); // GET for email verification link
$router->get('/api/auth/status', [AuthController::class, 'status']); // Check login status
$router->get('/api/csrf-token', [AuthController::class, 'getCsrfToken']); // Get CSRF token for forms

// Product & Related Entities API Routes (Publicly accessible for browsing)
$router->get('/api/products', [ProductController::class, 'index']); // Get all products with filters/pagination
$router->get('/api/products/{id_or_slug}', [ProductController::class, 'show']); // Get single product by ID or slug
$router->get('/api/categories', [CategoryController::class, 'index']); // Get all categories
$router->get('/api/categories/{id_or_slug}', [CategoryController::class, 'show']); // Get single category by ID or slug
$router->get('/api/platforms', [PlatformController::class, 'index']); // Get all platforms
$router->get('/api/platforms/{id_or_slug}', [PlatformController::class, 'show']); // Get single platform by ID or slug
$router->get('/api/publishers', [PublisherController::class, 'index']); // Get all publishers
$router->get('/api/publishers/{id_or_slug}', [PublisherController::class, 'show']); // Get single publisher by ID or slug
$router->get('/api/developers', [DeveloperController::class, 'index']); // Get all developers
$router->get('/api/developers/{id_or_slug}', [DeveloperController::class, 'show']); // Get single developer by ID or slug
$router->get('/api/tags', [TagController::class, 'index']); // Get all tags
$router->get('/api/tags/{id_or_slug}', [TagController::class, 'show']); // Get single tag by ID or slug
$router->get('/api/features', [FeatureController::class, 'index']); // Get all features for homepage

// Review System - Public Routes (no authentication required for reading reviews)
$router->get('/api/products/{id}/reviews', [ReviewController::class, 'getReviews']); // Get reviews for a product
$router->get('/api/products/{id}/review-status', [ReviewController::class, 'getReviewStatus']); // Check if user can review

// Public API route for contact form submission
$router->post('/api/contact/message', [ContactController::class, 'submit']);

// Group routes that require an authenticated user.
$router->group('/api', function(Router $router) {
    $router->post('/logout', [AuthController::class, 'logout']);
    
    // User Profile Management
    $router->get('/user/profile', [UserController::class, 'getProfile']);
    $router->put('/user/profile', [UserController::class, 'updateProfile']);
    $router->put('/user/change-password', [UserController::class, 'changePassword']);
    
    // User Address Management
    $router->get('/user/addresses', [UserController::class, 'getAddresses']);
    $router->post('/user/addresses', [UserController::class, 'addAddress']);
    $router->get('/user/addresses/{id}', [UserController::class, 'getAddress']);
    $router->put('/user/addresses/{id}', [UserController::class, 'updateAddress']);
    $router->delete('/user/addresses/{id}', [UserController::class, 'deleteAddress']);
    
    // User Game Library
    $router->get('/user/games', [UserController::class, 'getPurchasedGames']);
    
    // Cart Management Routes
    $router->post('/cart/add', [CartController::class, 'addItem']);
    $router->put('/cart/update', [CartController::class, 'updateItem']);
    $router->delete('/cart/remove', [CartController::class, 'removeItem']);
    $router->get('/cart', [CartController::class, 'getCart']);
    $router->post('/cart/apply-coupon', [CartController::class, 'applyCoupon']);
    $router->delete('/cart/remove-coupon', [CartController::class, 'removeCoupon']);
    
    // Wishlist Management Routes
    $router->post('/wishlist/add', [WishlistController::class, 'add']);
    $router->delete('/wishlist/remove', [WishlistController::class, 'remove']);
    $router->get('/wishlist', [WishlistController::class, 'getWishlist']);
    
    // Order Management Routes
    $router->post('/orders', [OrderController::class, 'createOrder']);
    $router->get('/orders', [OrderController::class, 'getUserOrders']);
    $router->get('/orders/{id}', [OrderController::class, 'getOrderDetails']);
    $router->get('/orders/{id}/keys', [OrderController::class, 'getOrderKeys']);
    
    // Review System - Authenticated Routes (require authentication for writing/voting)
    $router->post('/products/{id}/reviews', [ReviewController::class, 'addReview']); // Add a new review
    $router->post('/reviews/{id}/reply', [ReviewController::class, 'addReply']); // Reply to a review
    $router->post('/reviews/{id}/helpful', [ReviewController::class, 'markHelpful']); // Mark review as helpful/unhelpful
    
    // Newsletter subscription
    $router->post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
}, [AuthMiddleware::class]); // Apply AuthMiddleware to the entire group