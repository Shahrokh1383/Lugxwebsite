<?php
namespace App\Controllers\Admin;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\PageView;
use App\Models\DailySalesStat;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AuthService;
use PDO;
use Exception;

class AdminReportController extends Controller
{
    private ActivityLog $activityLogModel;
    private PageView $pageViewModel;
    private DailySalesStat $dailySalesStatModel;
    private Order $orderModel;
    private Product $productModel;
    private User $userModel;
    private AuthService $authService;
    
    public function __construct()
    {
        $this->activityLogModel = new ActivityLog();
        $this->pageViewModel = new PageView();
        $this->dailySalesStatModel = new DailySalesStat();
        $this->orderModel = new Order();
        $this->productModel = new Product();
        $this->userModel = new User();
        $this->authService = new AuthService();
    }
    
    //-------------------------------------------------------------
    // View Management
    //-------------------------------------------------------------
    /**
     * Renders the static HTML view for reports.
     * GET /admin/reports
     */
    public function index(): void
    {
        // This is a view handler. Authentication is handled by a middleware.
        $this->renderHtmlView('frontend/admin/admin_reports.html');
    }
    
    //-------------------------------------------------------------
    // API Endpoints
    // All API methods are protected by an authentication check.
    //-------------------------------------------------------------
    /**
     * Get sales reports.
     * GET /api/admin/reports/sales
     */
    public function getSalesReports(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        $period = $_GET['period'] ?? 'month'; // month, year, custom
        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        try {
            $salesData = [];
            
            switch ($period) {
                case 'month':
                    $salesData = $this->getMonthlySalesData($year);
                    break;
                case 'year':
                    $startYear = isset($_GET['start_year']) ? (int)$_GET['start_year'] : ($year - 4);
                    $endYear = $year;
                    $salesData = $this->getYearlySalesData($startYear, $endYear);
                    break;
                case 'custom':
                    if ($dateFrom && $dateTo) {
                        $salesData = $this->getCustomSalesData($dateFrom, $dateTo);
                    }
                    break;
            }
            
            $this->renderApiJson([
                'success' => true,
                'data' => $salesData,
                'period' => $period,
                'message' => 'Sales reports fetched successfully.'
            ]);
        } catch (Exception $e) {
            error_log("Error fetching sales reports: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Get top products.
     * GET /api/admin/reports/top-products
     */
    public function getTopProducts(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        $period = $_GET['period'] ?? 'month'; // week, month, year, all
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        try {
            $topProducts = $this->getTopProductsData($period, $limit);
            
            $this->renderApiJson([
                'success' => true,
                'data' => $topProducts,
                'period' => $period,
                'message' => 'Top products fetched successfully.'
            ]);
        } catch (Exception $e) {
            error_log("Error fetching top products: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Get user activity logs.
     * GET /api/admin/reports/user-activity
     */
    public function getUserActivity(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $action = $_GET['action'] ?? null;
        $modelType = $_GET['model_type'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = ($page - 1) * $limit;
        
        try {
            $filters = [];
            
            if ($userId) {
                $filters['user_id'] = $userId;
            }
            
            if ($action) {
                $filters['action'] = $action;
            }
            
            if ($modelType) {
                $filters['model_type'] = $modelType;
            }
            
            if ($dateFrom) {
                $filters['date_from'] = $dateFrom;
            }
            
            if ($dateTo) {
                $filters['date_to'] = $dateTo;
            }
            
            $logs = $this->activityLogModel->getLogs($filters, $limit, $offset);
            $totalLogs = $this->activityLogModel->countLogs($filters);
            
            $this->renderApiJson([
                'success' => true,
                'data' => $logs,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalLogs,
                    'pages' => ceil($totalLogs / $limit)
                ],
                'message' => 'User activity logs fetched successfully.'
            ]);
        } catch (Exception $e) {
            error_log("Error fetching user activity: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Get page views statistics.
     * GET /api/admin/reports/page-views
     */
    public function getPageViews(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        $reportType = $_GET['report_type'] ?? 'list'; // list, top_pages, device_stats, daily_stats
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        $url = $_GET['url'] ?? null;
        $deviceType = $_GET['device_type'] ?? null;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = ($page - 1) * $limit;
        
        try {
            $result = [];
            
            switch ($reportType) {
                case 'list':
                    $filters = [
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo
                    ];
                    
                    if ($url) {
                        $filters['url'] = $url;
                    }
                    
                    if ($deviceType) {
                        $filters['device_type'] = $deviceType;
                    }
                    
                    $pageViews = $this->pageViewModel->getPageViews($filters, $limit, $offset);
                    $totalViews = $this->pageViewModel->countPageViews($filters);
                    
                    $result = [
                        'data' => $pageViews,
                        'pagination' => [
                            'page' => $page,
                            'limit' => $limit,
                            'total' => $totalViews,
                            'pages' => ceil($totalViews / $limit)
                        ]
                    ];
                    break;
                    
                case 'top_pages':
                    $topPages = $this->pageViewModel->getTopPages($dateFrom, $dateTo, $limit);
                    $result = $topPages;
                    break;
                    
                case 'device_stats':
                    $deviceStats = $this->pageViewModel->getDeviceStats($dateFrom, $dateTo);
                    $result = $deviceStats;
                    break;
                    
                case 'daily_stats':
                    $dailyStats = $this->pageViewModel->getDailyStats($dateFrom, $dateTo);
                    $result = $dailyStats;
                    break;
            }
            
            $this->renderApiJson([
                'success' => true,
                'data' => $result,
                'report_type' => $reportType,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'message' => 'Page views statistics fetched successfully.'
            ]);
        } catch (Exception $e) {
            error_log("Error fetching page views: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Get general dashboard statistics.
     * GET /api/admin/reports/dashboard-stats
     */
    public function getDashboardStats(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        try {
            // Get basic counts
            $totalUsers = $this->userModel->countAll();
            $totalProducts = $this->productModel->countAll();
            $totalOrders = $this->orderModel->countAll();
            
            // Get recent sales data
            $currentMonth = date('Y-m');
            $lastMonth = date('Y-m', strtotime('-1 month'));
            
            $currentMonthSales = $this->dailySalesStatModel->getStatsByDateRange(
                $currentMonth . '-01', 
                $currentMonth . '-31'
            );
            
            $lastMonthSales = $this->dailySalesStatModel->getStatsByDateRange(
                $lastMonth . '-01', 
                $lastMonth . '-31'
            );
            
            $currentMonthRevenue = array_sum(array_column($currentMonthSales, 'revenue'));
            $lastMonthRevenue = array_sum(array_column($lastMonthSales, 'revenue'));
            
            $revenueChange = $lastMonthRevenue > 0 ? 
                (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;
            
            // Get top pages
            $topPages = $this->pageViewModel->getTopPages(
                date('Y-m-d', strtotime('-30 days')), 
                date('Y-m-d'), 
                5
            );
            
            // Get recent page views
            $recentPageViews = $this->pageViewModel->getPageViews(
                ['date_from' => date('Y-m-d', strtotime('-7 days'))], 
                5
            );
            
            $this->renderApiJson([
                'success' => true,
                'data' => [
                    'total_users' => $totalUsers,
                    'total_products' => $totalProducts,
                    'total_orders' => $totalOrders,
                    'current_month_revenue' => $currentMonthRevenue,
                    'revenue_change_percent' => round($revenueChange, 2),
                    'top_pages' => $topPages,
                    'recent_page_views' => $recentPageViews
                ],
                'message' => 'Dashboard statistics fetched successfully.'
            ]);
        } catch (Exception $e) {
            error_log("Error fetching dashboard stats: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Get monthly sales data.
     * @param int $year The year.
     * @return array The monthly sales data.
     */
    private function getMonthlySalesData(int $year): array
    {
        $monthlyStats = $this->dailySalesStatModel->getMonthlyStats($year);
        
        // Initialize all months with zero values
        $result = [];
        for ($month = 1; $month <= 12; $month++) {
            $result[$month - 1] = [
                'month' => $month,
                'orders_count' => 0,
                'revenue' => 0,
                'new_customers' => 0,
                'returning_customers' => 0,
                'products_sold' => 0,
                'avg_order_value' => 0
            ];
        }
        
        // Fill with actual data
        foreach ($monthlyStats as $stat) {
            $monthIndex = $stat['month'] - 1;
            $result[$monthIndex] = $stat;
        }
        
        return array_values($result);
    }
    
    /**
     * Get yearly sales data.
     * @param int $startYear The start year.
     * @param int $endYear The end year.
     * @return array The yearly sales data.
     */
    private function getYearlySalesData(int $startYear, int $endYear): array
    {
        $result = [];
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearlyStats = $this->dailySalesStatModel->getStatsByDateRange(
                $year . '-01-01', 
                $year . '-12-31'
            );
            
            $totalOrders = 0;
            $totalRevenue = 0;
            $totalNewCustomers = 0;
            $totalReturningCustomers = 0;
            $totalProductsSold = 0;
            $totalAvgOrderValue = 0;
            
            foreach ($yearlyStats as $stat) {
                $totalOrders += $stat['orders_count'];
                $totalRevenue += $stat['revenue'];
                $totalNewCustomers += $stat['new_customers'];
                $totalReturningCustomers += $stat['returning_customers'];
                $totalProductsSold += $stat['products_sold'];
                $totalAvgOrderValue += $stat['avg_order_value'];
            }
            
            $result[] = [
                'year' => $year,
                'orders_count' => $totalOrders,
                'revenue' => $totalRevenue,
                'new_customers' => $totalNewCustomers,
                'returning_customers' => $totalReturningCustomers,
                'products_sold' => $totalProductsSold,
                'avg_order_value' => $totalAvgOrderValue > 0 ? $totalAvgOrderValue / count($yearlyStats) : 0
            ];
        }
        
        return $result;
    }
    
    /**
     * Get custom sales data.
     * @param string $dateFrom The start date.
     * @param string $dateTo The end date.
     * @return array The custom sales data.
     */
    private function getCustomSalesData(string $dateFrom, string $dateTo): array
    {
        return $this->dailySalesStatModel->getStatsByDateRange($dateFrom, $dateTo);
    }
    
    /**
     * Get top products data.
     * @param string $period The period (week, month, year, all).
     * @param int $limit The limit.
     * @return array The top products data.
     */
    private function getTopProductsData(string $period, int $limit): array
    {
        // Calculate date range based on period
        $dateFrom = null;
        $dateTo = date('Y-m-d');
        
        switch ($period) {
            case 'week':
                $dateFrom = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month':
                $dateFrom = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'year':
                $dateFrom = date('Y-m-d', strtotime('-365 days'));
                break;
        }
        
        // If no date range, get all time data
        if ($dateFrom === null) {
            $dateFrom = '2020-01-01'; // A reasonable start date
        }
        
        // This is a complex query that joins orders, order_items, and products
        // In a real implementation, you would create a view or use a complex query
        // For now, we'll return a placeholder result
        
        // Query structure:
        // SELECT p.id, p.title, p.slug, p.featured_image, SUM(oi.quantity) as total_sold, SUM(oi.total) as total_revenue
        // FROM products p
        // JOIN order_items oi ON p.id = oi.product_id
        // JOIN orders o ON oi.order_id = o.id
        // WHERE o.status = 'completed' AND o.created_at BETWEEN :date_from AND :date_to
        // GROUP BY p.id
        // ORDER BY total_sold DESC
        // LIMIT :limit
        
        // For now, return an empty array
        return [];
    }
}