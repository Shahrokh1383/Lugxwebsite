<?php
namespace App\Controllers\Api;
use App\Core\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Platform;
use App\Models\Tag;
use App\Models\Publisher;
use App\Models\Developer;
use App\Models\ProductReview;
use App\Models\Order;
/**
 * Product API Controller
 * Handles product-related API requests.
 */
class ProductController extends Controller
{
    private Product $productModel;
    private Category $categoryModel;
    private Platform $platformModel;
    private Tag $tagModel;
    private Publisher $publisherModel;
    private Developer $developerModel;
    private ProductReview $productReviewModel;
    private Order $orderModel;
    
    public function __construct()
    {
        // Start session if not already started, crucial for accessing $_SESSION
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->productModel = $this->model('Product');
        $this->categoryModel = $this->model('Category');
        $this->platformModel = $this->model('Platform');
        $this->tagModel = $this->model('Tag');
        $this->publisherModel = $this->model('Publisher');
        $this->developerModel = $this->model('Developer');
        $this->productReviewModel = $this->model('ProductReview');
        $this->orderModel = $this->model('Order');
        
        if (!$this->productModel || !$this->categoryModel || !$this->platformModel || !$this->tagModel || !$this->publisherModel || !$this->developerModel || !$this->productReviewModel || !$this->orderModel) {
            error_log("Failed to load one or more models in ProductController.");
            $this->renderApiJson(['message' => 'Internal server error: Models could not be loaded.'], 500);
            exit;
        }
    }
    
    /**
     * Get a list of products.
     *
     * GET /api/products
     * Example: /api/products?limit=12&page=1&category=action&platform=pc&search=game&sort_by=price&sort_order=asc&trending=true
     */
    public function index(): void
    {
        // Sanitize and validate input
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 9; // Changed default limit to 9 as per product.js
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1; // Default page
        $categorySlug = (string) filter_input(INPUT_GET, 'category');
        $platformSlug = (string) filter_input(INPUT_GET, 'platform');
        $searchQuery = (string) filter_input(INPUT_GET, 'search');
        $tagSlug = (string) filter_input(INPUT_GET, 'tag');
        $publisherSlug = (string) filter_input(INPUT_GET, 'publisher');
        $developerSlug = (string) filter_input(INPUT_GET, 'developer');
        $sortBy = (string) filter_input(INPUT_GET, 'sort_by');
        $sortOrder = (string) filter_input(INPUT_GET, 'sort_order');
        // NEW: Get 'trending' parameter
        $isTrending = filter_input(INPUT_GET, 'trending', FILTER_VALIDATE_BOOLEAN);
        
        // Default sort_by/sort_order if not provided or invalid
        $allowedSortBy = ['created_at', 'name', 'price', 'release_date', 'average_rating', 'sales_count', 'views_count', 'downloads_count'];
        $sortBy = in_array($sortBy, $allowedSortBy) ? $sortBy : 'created_at';
        $sortOrder = (strtolower($sortOrder) === 'asc') ? 'ASC' : 'DESC';
        
        // Build filters array for the Product model's getAll method
        $filters = [];
        
        // Convert slugs to IDs for filters
        if (!empty($categorySlug)) {
            $category = $this->categoryModel->findBySlug($categorySlug);
            if ($category) {
                $filters['category_id'] = $category['id'];
            } else {
                $this->renderApiJson(['message' => 'Category not found.'], 404);
                return;
            }
        }
        
        if (!empty($platformSlug)) {
            $platform = $this->platformModel->findBySlug($platformSlug);
            if ($platform) {
                $filters['platform_id'] = $platform['id'];
            } else {
                $this->renderApiJson(['message' => 'Platform not found.'], 404);
                return;
            }
        }
        
        if (!empty($tagSlug)) {
            $tag = $this->tagModel->findBySlug($tagSlug);
            if ($tag) {
                $filters['tag_id'] = $tag['id'];
            } else {
                $this->renderApiJson(['message' => 'Tag not found.'], 404);
                return;
            }
        }
        
        if (!empty($publisherSlug)) {
            $publisher = $this->publisherModel->findBySlug($publisherSlug);
            if ($publisher) {
                $filters['publisher_id'] = $publisher['id'];
            } else {
                $this->renderApiJson(['message' => 'Publisher not found.'], 404);
                return;
            }
        }
        
        if (!empty($developerSlug)) {
            $developer = $this->developerModel->findBySlug($developerSlug);
            if ($developer) {
                $filters['developer_id'] = $developer['id'];
            } else {
                $this->renderApiJson(['message' => 'Developer not found.'], 404);
                return;
            }
        }
        
        if (!empty($searchQuery)) {
            $filters['search_query'] = $searchQuery;
        }
        
        // NEW: Add is_trending filter if the 'trending' parameter is true
        if ($isTrending) {
            $filters['is_trending'] = true;
        }
        
        // Fetch products using the Product model's getAll method
        $result = $this->productModel->getAll(
            $filters,
            $page,
            $limit,
            $sortBy,
            $sortOrder
        );
        
        $products = $result['products'];
        $totalProducts = $result['total_products'];
        
        // Add related data (categories, platforms, tags, publisher, developer) for each product
        foreach ($products as &$product) {
            // Convert price fields to float to ensure correct JSON type for JavaScript's toFixed()
            $product['price'] = (float) $product['price'];
            $product['sale_price'] = (float) $product['sale_price'];
            // Ensure discount_percentage is float
            $product['discount_percentage'] = (float) $product['discount_percentage'];
            
            // Categories (using getCategories from Product model)
            $productCategories = $this->productModel->getCategories($product['id']);
            $product['categories'] = array_map(function($pc) {
                return ['id' => $pc['id'], 'name' => $pc['name'], 'slug' => $pc['slug'], 'is_primary' => (bool)$pc['is_primary']];
            }, $productCategories);
            $product['categories'] = array_values(array_filter($product['categories']));
            
            // Platforms (using getPlatforms from Product model)
            $productPlatforms = $this->productModel->getPlatforms($product['id']);
            $product['platforms'] = array_map(function($pp) {
                return ['id' => $pp['id'], 'name' => $pp['name'], 'slug' => $pp['slug'], 'icon' => $pp['icon']];
            }, $productPlatforms);
            $product['platforms'] = array_values(array_filter($product['platforms']));
            
            // Tags (using getTags from Product model)
            $productTags = $this->productModel->getTags($product['id']);
            $product['tags'] = array_map(function($pt) {
                return ['id' => $pt['id'], 'name' => $pt['name'], 'slug' => $pt['slug'], 'color' => $pt['color']];
            }, $productTags);
            $product['tags'] = array_values(array_filter($product['tags']));
            
            // Publisher & Developer details (using current controller's approach)
            if (!empty($product['publisher_id'])) {
                $publisher = $this->publisherModel->findById($product['publisher_id']);
                $product['publisher'] = $publisher ? ['id' => $publisher['id'], 'name' => $publisher['name'], 'slug' => $publisher['slug'], 'logo' => $publisher['logo'], 'website' => $publisher['website']] : null;
            } else {
                $product['publisher'] = null;
            }
            
            if (!empty($product['developer_id'])) {
                $developer = $this->developerModel->findById($product['developer_id']);
                $product['developer'] = $developer ? ['id' => $developer['id'], 'name' => $developer['name'], 'slug' => $developer['slug'], 'logo' => $developer['logo'], 'website' => $developer['website']] : null;
            } else {
                $product['developer'] = null;
            }
            
            // Note: gallery and system_requirements are already decoded in Product::findById/findBySlug/getAll
            // No need to decode again here if coming from getAll which already decodes for each product.
            // Ensure they are arrays, even if empty or null from DB.
            $product['gallery'] = $product['gallery'] ?? [];
            $product['system_requirements'] = $product['system_requirements'] ?? [];
        }
        unset($product); // Unset reference after loop
        
        // Corrected JSON structure to match product.js expectation
        $this->renderApiJson([
            'status' => 'success',
            'data' => [ // Wrap products, total, page, limit inside a 'data' object
                'products' => $products,
                'total_products' => $totalProducts,
                'current_page' => $page,
                'limit' => $limit
            ]
        ]);
    }
    
    /**
     * Get a single product by ID or slug.
     *
     * GET /api/products/{id_or_slug}
     */
    public function show(string $idOrSlug): void
    {
        $product = null;
        // Check if it's an integer ID or a slug
        if (is_numeric($idOrSlug)) {
            $product = $this->productModel->findById((int)$idOrSlug);
        } else {
            $product = $this->productModel->findBySlug($idOrSlug);
        }
        
        if (!$product || $product['status'] !== 'published') {
            $this->renderApiJson(['message' => 'Product not found or not published.'], 404);
            return;
        }
        
        // Convert price fields to float for single product as well
        $product['price'] = (float) $product['price'];
        $product['sale_price'] = (float) $product['sale_price'];
        $product['discount_percentage'] = (float) $product['discount_percentage'];
        
        // Get average rating and review count
        $ratingStats = $this->productReviewModel->getAverageRatingAndCount($product['id']);
        $product['average_rating'] = (float) $ratingStats['average_rating'];
        $product['reviews_count'] = (int) $ratingStats['review_count'];
        
        // Fetch related data for the single product
        // Categories (using getCategories from Product model)
        $productCategories = $this->productModel->getCategories($product['id']);
        $product['categories'] = array_map(function($pc) {
            return ['id' => $pc['id'], 'name' => $pc['name'], 'slug' => $pc['slug'], 'is_primary' => (bool)$pc['is_primary']];
        }, $productCategories);
        $product['categories'] = array_values(array_filter($product['categories']));
        
        // Platforms (using getPlatforms from Product model)
        $productPlatforms = $this->productModel->getPlatforms($product['id']);
        $product['platforms'] = array_map(function($pp) {
            return ['id' => $pp['id'], 'name' => $pp['name'], 'slug' => $pp['slug'], 'icon' => $pp['icon']];
        }, $productPlatforms);
        $product['platforms'] = array_values(array_filter($product['platforms']));
        
        // Tags (using getTags from Product model)
        $productTags = $this->productModel->getTags($product['id']);
        $product['tags'] = array_map(function($pt) {
            return ['id' => $pt['id'], 'name' => $pt['name'], 'slug' => $pt['slug'], 'color' => $pt['color']];
        }, $productTags);
        $product['tags'] = array_values(array_filter($product['tags']));
        
        // Related Products (using getRelatedProducts from Product model)
        $relatedProducts = $this->productModel->getRelatedProducts($product['id']);
        $product['related_products'] = array_map(function($rp) {
            // Convert price fields to float for related products too
            $rp['price'] = (float) $rp['price'];
            $rp['sale_price'] = (float) $rp['sale_price'];
            return ['id' => $rp['id'], 'name' => $rp['name'], 'slug' => $rp['slug'], 'thumbnail' => $rp['main_image_url'], 'price' => $rp['price'], 'sale_price' => $rp['sale_price'], 'average_rating' => (float)$rp['average_rating']];
        }, $relatedProducts);
        $product['related_products'] = array_values(array_filter($product['related_products']));
        
        // Publisher & Developer details
        if (!empty($product['publisher_id'])) {
            $publisher = $this->publisherModel->findById($product['publisher_id']);
            $product['publisher'] = $publisher ? ['id' => $publisher['id'], 'name' => $publisher['name'], 'slug' => $publisher['slug'], 'logo' => $publisher['logo'], 'website' => $publisher['website']] : null;
        } else {
            $product['publisher'] = null;
        }
        
        if (!empty($product['developer_id'])) {
            $developer = $this->developerModel->findById($product['developer_id']);
            $product['developer'] = $developer ? ['id' => $developer['id'], 'name' => $developer['name'], 'slug' => $developer['slug'], 'logo' => $developer['logo'], 'website' => $developer['website']] : null;
        } else {
            $product['developer'] = null;
        }
        
        // Ensure gallery and system_requirements are arrays
        $product['gallery'] = $product['gallery'] ?? [];
        $product['system_requirements'] = $product['system_requirements'] ?? [];
        
        // Add user-specific review and purchase status
        $userId = $_SESSION['user_id'] ?? null;
        $product['user_has_purchased'] = false;
        $product['user_has_reviewed'] = false;
        
        if ($userId) {
            // Check if user has purchased this product
            $product['user_has_purchased'] = $this->orderModel->hasUserPurchasedProduct((int)$userId, $product['id']);
            // Check if user has already reviewed this product - استفاده از متد اصلاح شده
            $existingReview = $this->productReviewModel->findByUserAndProduct((int)$userId, $product['id']);
            $product['user_has_reviewed'] = !empty($existingReview);
        }
        
        $this->renderApiJson(['status' => 'success', 'data' => $product]);
    }
}