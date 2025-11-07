<?php
namespace App\Core;

use App\Controllers\FrontendController;
use PDOException; // Import PDOException for specific error handling

class App
{
    protected Router $router;

    public function __construct()
    {
        error_log("DEBUG: App::__construct - Called."); // ADDED DEBUG LOG
        // Composer's autoloader and constants are loaded in public/index.php.
        // We only need to load route definition files here.
        try {
            $this->router = new Router(); // Router instance is created here
            error_log("DEBUG: App::__construct - Router instantiated."); // ADDED DEBUG LOG
        } catch (\Throwable $th) {
            error_log("CRITICAL ERROR: App::__construct - Failed to instantiate Router: " . $th->getMessage() . " in " . $th->getFile() . " on line " . $th->getLine());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Internal server error during router initialization.']);
            exit;
        }

        $this->loadRoutes();
        error_log("DEBUG: App::__construct - Routes loaded."); // ADDED DEBUG LOG
    }

    private function loadRoutes(): void
    {
        error_log("DEBUG: App::loadRoutes - Called."); // ADDED DEBUG LOG
        // Make the router instance available within the scope
        // of the included route files (api.php and admin_api.php).
        $router = $this->router;

        // Load API routes.
        // Make sure these files are present and contain valid route definitions.
        $apiRoutesPath = ROOT_PATH . '/app/routes/api.php';
        $adminApiRoutesPath = ROOT_PATH . '/app/routes/admin_api.php';

        if (file_exists($apiRoutesPath)) {
            error_log("DEBUG: App::loadRoutes - Including api.php: " . $apiRoutesPath); // ADDED DEBUG LOG
            require_once $apiRoutesPath;
        } else {
            error_log("CRITICAL ERROR: App::loadRoutes - api.php not found: " . $apiRoutesPath); // ADDED DEBUG LOG
        }

        if (file_exists($adminApiRoutesPath)) {
            error_log("DEBUG: App::loadRoutes - Including admin_api.php: " . $adminApiRoutesPath); // ADDED DEBUG LOG
            require_once $adminApiRoutesPath;
        } else {
            error_log("CRITICAL ERROR: App::loadRoutes - admin_api.php not found: " . $adminApiRoutesPath); // ADDED DEBUG LOG
        }

        // Frontend HTML page routes, handled by FrontendController.
        // These routes redirect to static HTML files now located within the public/frontend/ directory.
        $this->router->get('/', [FrontendController::class, 'index']);
        $this->router->get('/shop', [FrontendController::class, 'shop']);
        $this->router->get('/product/{id}', [FrontendController::class, 'productDetail']);
        $this->router->get('/cart', [FrontendController::class, 'cart']);
        $this->router->get('/checkout', [FrontendController::class, 'checkout']);
        $this->router->get('/login', [FrontendController::class, 'login']);
        $this->router->get('/register', [FrontendController::class, 'register']);
        $this->router->get('/forgot-password', [FrontendController::class, 'forgotPassword']);
        $this->router->get('/reset-password', [FrontendController::class, 'resetPassword']);
        $this->router->get('/user-dashboard', [FrontendController::class, 'userDashboard']);
        $this->router->get('/user-profile', [FrontendController::class, 'userProfile']);
        $this->router->get('/user-orders', [FrontendController::class, 'userOrders']);
        $this->router->get('/user-order-detail/{id}', [FrontendController::class, 'userOrderDetail']);
        $this->router->get('/user-addresses', [FrontendController::class, 'userAddresses']);
        $this->router->get('/wishlist', [FrontendController::class, 'wishlist']);
        $this->router->get('/contact', [FrontendController::class, 'contact']);
        $this->router->get('/about', [FrontendController::class, 'about']);
        $this->router->get('/terms-conditions', [FrontendController::class, 'termsConditions']);
        $this->router->get('/privacy-policy', [FrontendController::class, 'privacyPolicy']);

        // Admin Frontend HTML page routes.
        $this->router->get('/admin/login', [FrontendController::class, 'adminLogin']);
        $this->router->get('/admin/dashboard', [FrontendController::class, 'adminDashboard']);
        $this->router->get('/admin/products', [FrontendController::class, 'adminProducts']);
        $this->router->get('/admin/products/add', [FrontendController::class, 'adminProductAddEdit']);
        $this->router->get('/admin/products/edit/{id}', [FrontendController::class, 'adminProductAddEdit']);
        $this->router->get('/admin/categories', [FrontendController::class, 'adminCategories']);
        $this->router->get('/admin/orders', [FrontendController::class, 'adminOrders']);
        $this->router->get('/admin/orders/detail/{id}', [FrontendController::class, 'adminOrderDetail']);
        $this->router->get('/admin/users', [FrontendController::class, 'adminUsers']);
        $this->router->get('/admin/reviews', [FrontendController::class, 'adminReviews']);
        $this->router->get('/admin/coupons', [FrontendController::class, 'adminCoupons']);
        $this->router->get('/admin/settings', [FrontendController::class, 'adminSettings']);
        $this->router->get('/admin/messages', [FrontendController::class, 'adminMessages']);
        $this->router->get('/admin/newsletter', [FrontendController::class, 'adminNewsletter']);
        $this->router->get('/admin/banners', [FrontendController::class, 'adminBanners']);
    }

    public function run(): void
    {
        error_log("DEBUG: App::run - Called. Attempting to resolve route."); // ADDED DEBUG LOG

        // MANUAL CLASS EXISTENCE CHECK (TEMPORARY FOR DEBUGGING)
        // Let's explicitly check if CartController can be found by the autoloader *before* router tries to instantiate it.
        $controllerClass = 'App\\Controllers\\Api\\CartController';
        if (!class_exists($controllerClass)) {
            error_log("CRITICAL ERROR: App::run - Class not found before resolve(): " . $controllerClass);
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Internal server error: Core controller not found. (Autoloader issue)']);
            exit;
        } else {
            error_log("DEBUG: App::run - Class exists: " . $controllerClass);
        }
        // END MANUAL CLASS EXISTENCE CHECK

        try {
            $this->router->resolve();
        } catch (PDOException $e) {
            // Handle database connection errors gracefully for API calls
            // This will send a JSON response to the browser instead of a Fatal Error
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database connection error. Please try again later.',
                'debug' => APP_ENV === 'development' ? $e->getMessage() : null // Only show detailed error in development
            ]);
            error_log("Unhandled PDOException in App::run(): " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine()); // Added file and line
        } catch (\Throwable $e) { // Changed to \Throwable to catch all errors and exceptions
            // Catch any other unexpected exceptions or errors that might occur during routing or controller execution
            // Ensure headers haven't been sent before attempting to set JSON header
            if (!headers_sent()) {
                header('Content-Type: application/json');
                http_response_code(500);
            } else {
                // If headers already sent, we can't send JSON; fallback to plain text and log
                error_log("CRITICAL ERROR: Headers already sent when catching unhandled \Throwable in App::run(). Outputting plain error.");
                http_response_code(500); // Still set status code if possible
            }

            $errorMessage = 'An unexpected server error occurred.';
            $debugMessage = null;

            if (defined('APP_ENV') && APP_ENV === 'development') { // Check if APP_ENV is defined and in development mode
                $debugMessage = $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
                // If headers are already sent, we just echo the debug message directly
                if (headers_sent()) {
                    echo "<h1>Unhandled Error!</h1><p>Type: " . get_class($e) . "</p><p>Message: " . htmlspecialchars($e->getMessage()) . "</p><p>File: " . htmlspecialchars($e->getFile()) . "</p><p>Line: " . $e->getLine() . "</p>";
                    error_log("Unhandled Throwable in App::run() (headers sent): " . $debugMessage);
                    exit; // Terminate early if headers sent and debug info printed
                }
            }
            
            echo json_encode([
                'status' => 'error',
                'message' => $errorMessage,
                'debug' => $debugMessage
            ], JSON_THROW_ON_ERROR); // Added JSON_THROW_ON_ERROR for safety

            error_log("Unhandled Throwable in App::run(): " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        } finally {
            // Ensure script terminates after handling the request or error
            // Only exit if not already exited by a direct echo + exit from headers_sent branch
            if (!headers_sent()) { // This check is mostly for the browser output, but good practice
                exit; 
            }
        }
    }
}
