<?php
namespace App\Controllers\Admin;
use App\Core\Controller;
use App\Services\AuthService;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\ContactMessage;
use PDOException;
class AdminDashboardController extends Controller
{
    private AuthService $authService;
    private User $userModel;
    private Order $orderModel;
    private Product $productModel;
    private ContactMessage $contactMessageModel;
    public function __construct()
    {
        // Initializes the AuthService to handle user authentication checks.
        $this->authService = new AuthService();
        
        // Initializes models to fetch data for the dashboard.
        $this->userModel = new User();
        $this->orderModel = new Order();
        $this->productModel = new Product();
        $this->contactMessageModel = new ContactMessage();
    }
    /**
     * Displays the main admin dashboard view (HTML).
     * GET /admin/dashboard
     * This method renders the HTML page, and the data for it will be fetched via AJAX
     * by the frontend JavaScript calling the getDashboardStats API endpoint.
     */
    public function index(): void
    {
        // Start a new session if one is not already active.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // 1. Check for authentication and admin role.
        // This check is crucial for direct access to the HTML page.
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            // If user is not logged in or is not an admin, deny access.
            // Redirect to the admin login page.
            $this->redirect('/admin/login');
            return;
        }
        
        // Render the dashboard HTML view.
        // The actual statistics data will be loaded via an AJAX call to getDashboardStats.
        $this->view('admin.admin_dashboard', ['pageTitle' => 'Admin Dashboard']);
    }
    /**
     * Retrieves dashboard statistics as a JSON API response.
     * GET /api/admin/dashboard/stats
     * This endpoint is protected by AdminMiddleware.
     */
    public function getDashboardStats(): void
    {
        // Start a new session if one is not already active.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // AdminMiddleware should handle the authentication and role check before this method is called.
        // However, adding a redundant check here for robustness is not harmful, but typically handled by middleware.
        // For API endpoints, we respond with JSON errors if checks fail.
        if (!$this->authService->isLoggedIn()) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized. Please log in.'], 401);
            return;
        }
        if (!$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Forbidden. You do not have permission to access this resource.'], 403);
            return;
        }
        try {
            // Fetch total counts from the database using models.
            $totalUsers = $this->userModel->countAll();
            $totalProducts = $this->productModel->countAll();
            $totalOrders = $this->orderModel->countAll();
            
            // Fetch a list of the 5 most recent orders.
            $recentOrders = $this->orderModel->findLast(5);
            // Fetch a list of the 5 most recent users.
            $recentUsers = $this->userModel->findLast(5);
            // Fetch count of new messages (changed from countUnread to countNew).
            $unreadMessagesCount = $this->contactMessageModel->countNew();
            
            // Prepare data to be sent as JSON.
            $stats = [
                'totalUsers' => $totalUsers,
                'totalProducts' => $totalProducts,
                'totalOrders' => $totalOrders,
                'recentOrders' => $recentOrders,
                'recentUsers' => $recentUsers,
                'unreadMessagesCount' => $unreadMessagesCount
            ];
            // Respond with the fetched statistics as JSON.
            $this->renderApiJson([
                'success' => true,
                'message' => 'Dashboard statistics fetched successfully.',
                'data' => $stats
            ]);
        } catch (PDOException $e) {
            // Log the database error.
            error_log("ERROR: AdminDashboardController::getDashboardStats - Database error: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error. Could not retrieve dashboard statistics.'], 500);
        } catch (\Exception $e) { // Changed from PDOException to \Exception to catch all exceptions
            // Log any other unexpected errors.
            error_log("ERROR: AdminDashboardController::getDashboardStats - Unexpected error: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred while fetching dashboard statistics.'], 500);
        }
    }
}