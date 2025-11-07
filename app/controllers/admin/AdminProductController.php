<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Platform;
use App\Models\Developer;
use App\Models\Publisher;
use App\Models\Tag;
use App\Services\AuthService;
use App\Services\UploadService;
use App\Services\ValidationService;
use Exception;
use PDOException;

/**
 * Controller for managing products in the admin panel.
 * Handles CRUD operations via API endpoints and renders the admin product view.
 */
class AdminProductController extends Controller
{
    private Product $productModel;
    private Category $categoryModel;
    private Platform $platformModel;
    private Developer $developerModel;
    private Publisher $publisherModel;
    private Tag $tagModel;
    private UploadService $uploadService;
    private AuthService $authService;
    private ValidationService $validator;
    
    public function __construct()
    {
        parent::__construct();
        $this->productModel = new Product();
        $this->categoryModel = new Category();
        $this->platformModel = new Platform();
        $this->developerModel = new Developer();
        $this->publisherModel = new Publisher();
        $this->tagModel = new Tag();
        $this->uploadService = new UploadService();
        $this->authService = new AuthService();
        $this->validator = new ValidationService();
    }
    
    //-------------------------------------------------------------
    // View Management
    //-------------------------------------------------------------
    /**
     * Renders the static HTML view for managing products.
     * GET /admin/products
     */
    public function index(): void
    {
        $this->renderHtmlView('frontend/admin/admin_products.html');
    }
    
    //-------------------------------------------------------------
    // API Endpoints
    //-------------------------------------------------------------
    /**
     * Get a list of all products with search and pagination for the admin panel.
     * GET /api/admin/products
     */
    public function indexApi(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }
        try {
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 10);
            $search = $_GET['search'] ?? '';
            $offset = ($page - 1) * $limit;
            $result = $this->productModel->getAllForAdmin($limit, $offset, $search);
            $response = [
                'success' => true,
                'data' => $result['products'],
                'current_page' => $page,
                'total_pages' => ceil($result['total_products'] / $limit),
                'total_products' => (int) $result['total_products']
            ];
            $this->renderApiJson($response);
        }catch (PDOException $e) {
            error_log("Error fetching products: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Get details for a single product.
     * GET /api/admin/products/{id}
     * @param int $id
     */
    public function show(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }
        
        try {
            $product = $this->productModel->findByIdWithDetails($id);
            if (!$product) {
                $this->renderApiJson(['success' => false, 'message' => 'Product not found.'], 404);
                return;
            }
            
            // Flatten the image structure for the frontend
            $gallery = isset($product['gallery']) && is_string($product['gallery']) ? json_decode($product['gallery'], true) : [];
            $allImages = array_merge([$product['featured_image']], $gallery);
            $product['images'] = array_map(function($path) use ($product) {
                return [
                    'id' => uniqid(), // Use a unique ID for the frontend to manage existing images
                    'image_url' => $path,
                    'is_featured' => ($path == $product['featured_image'])
                ];
            }, $allImages);
            
            // Remove redundant fields
            unset($product['featured_image']);
            unset($product['gallery']);
            
            // Process system requirements to separate minimum and recommended
            $systemRequirements = isset($product['system_requirements']) && is_string($product['system_requirements']) ? json_decode($product['system_requirements'], true) : [];
            
            // If system_requirements is in the old format (flat), convert it to the new format
            if (!empty($systemRequirements) && !isset($systemRequirements['minimum']) && !isset($systemRequirements['recommended'])) {
               $product['min_requirements'] = $systemRequirements;
               $product['rec_requirements'] = [];
            } else {
                $product['min_requirements'] = $systemRequirements['minimum'] ?? [];
                $product['rec_requirements'] = $systemRequirements['recommended'] ?? [];
            }
            
            // Remove the original system_requirements field
            unset($product['system_requirements']);
            
            // Get activation keys using the correct column name
            $product['keys'] = $this->productModel->getActivationKeys($id);
            
            // Get related products
            $product['related_products'] = $this->productModel->getRelatedProducts($id);
        
            $this->renderApiJson(['success' => true, 'data' => $product]);
        }catch (Exception $e) {
            error_log("Error fetching product: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Create a new product.
     * POST /api/admin/products
     */
    public function store(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }
        
        $data = $this->getPostData();
        $files = $_FILES;
        
        // Validation rules based on the provided database schema
        $rules = [
            'title' => 'required|max:255',
            'slug' => 'required|max:255|unique:products,slug',
            'price' => 'required|numeric|min:0',
            'key_count' => 'required|integer|min:0',
            'stock_status' => 'required|in:in_stock,out_of_stock,pre_order',
            'release_date' => 'required|date',
            'developer_id' => 'required|integer',
            'publisher_id' => 'required|integer',
            'short_description' => 'required',
            'long_description' => 'required',
            'featured_image' => 'required|file:image',
            'system_requirements' => 'nullable|json',
            'status' => 'required|in:draft,published,archived',
            'sku' => 'nullable|max:100|unique:products,sku',
            'video_trailer' => 'nullable|max:255',
            'age_rating' => 'nullable|in:E,E10+,T,M,AO,RP',
            'file_size' => 'nullable|max:50',
            'meta_title' => 'nullable|max:255',
            'meta_description' => 'nullable',
        ];
        
        $validationData = array_merge($data, $files);
        if (!$this->validator->validate($validationData, $rules)) {
            $this->renderApiJson(['success' => false, 'message' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }
        
        try {
            $featuredImagePath = $this->uploadService->uploadFile($files['featured_image'], 'products/featured');
            $galleryPaths = [];
            if (isset($files['images']) && $files['images']['error'][0] === UPLOAD_ERR_OK) {
                $galleryPaths = $this->uploadService->uploadMultipleFiles($files['images'], 'products/gallery');
            }
            
            $price = (float) $data['price'];
            
            // Handle sale_price with proper validation
            $salePrice = null;
            if (isset($data['sale_price']) && is_numeric($data['sale_price'])) {
                $salePrice = (float) $data['sale_price'];
                // Ensure sale_price is less than price
                if ($salePrice >= $price) {
                    $salePrice = null; // Set to null if not valid
                }
            }
            
            $discountPercentage = 0;
            if ($salePrice !== null && $price > 0) {
                $discountPercentage = round((($price - $salePrice) / $price) * 100, 2);
            }
            
            // Process system requirements to combine minimum and recommended
            $minRequirements = isset($data['min_requirements']) ? json_decode($data['min_requirements'], true) : [];
            $recRequirements = isset($data['rec_requirements']) ? json_decode($data['rec_requirements'], true) : [];
            
            // Combine minimum and recommended requirements into a single JSON structure
            $systemRequirements = [
                'minimum' => $minRequirements,
                'recommended' => $recRequirements
            ];
            
            // Prepare product data for database insertion
            $productData = [
                'title' => $data['title'],
                'slug' => $data['slug'],
                'short_description' => $data['short_description'],
                'description' => $data['long_description'],
                'price' => $price,
                'sale_price' => $salePrice,
                'discount_percentage' => $discountPercentage,
                'key_count' => (int) $data['key_count'],
                'stock_status' => $data['stock_status'],
                'featured_image' => $featuredImagePath,
                'gallery' => json_encode($galleryPaths),
                'release_date' => $data['release_date'],
                'publisher_id' => (int) $data['publisher_id'],
                'developer_id' => (int) $data['developer_id'],
                'system_requirements' => json_encode($systemRequirements),
                'is_featured' => isset($data['is_featured']) ? 1 : 0,
                'is_trending' => isset($data['is_trending']) ? 1 : 0,
                'status' => $data['status'],
                'sku' => $data['sku'] ?? null,
                'video_trailer' => $data['video_trailer'] ?? null,
                'age_rating' => $data['age_rating'] ?? null,
                'file_size' => $data['file_size'] ?? null,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ];
            
            $productId = $this->productModel->create($productData);
            if (!$productId) {
                // If product creation fails, delete uploaded files
                $this->uploadService->deleteFile($featuredImagePath);
                foreach ($galleryPaths as $path) { 
                    $this->uploadService->deleteFile($path); 
                }
                $this->renderApiJson(['success' => false, 'message' => 'Failed to create product.'], 500);
                return;
            }
            
            // Sync relationships (categories, platforms, tags)
            $categories = isset($data['categories']) ? (is_array($data['categories']) ? $data['categories'] : explode(',', $data['categories'])) : [];
            $platforms = isset($data['platforms']) ? (is_array($data['platforms']) ? $data['platforms'] : explode(',', $data['platforms'])) : [];
            $tags = isset($data['tags']) ? (is_array($data['tags']) ? $data['tags'] : explode(',', $data['tags'])) : [];
            
            $this->productModel->syncRelationships($productId, 'categories', $categories);
            $this->productModel->syncRelationships($productId, 'platforms', $platforms);
            $this->productModel->syncRelationships($productId, 'tags', $tags);
            
            // Add activation keys
            if (isset($data['keys']) && is_string($data['keys'])) {
                $keys = json_decode($data['keys'], true);
                if (is_array($keys)) {
                    $this->productModel->addKeys($productId, $keys);
                }
            }
            
            // Add related products if provided
            if (isset($data['related_products']) && is_array($data['related_products'])) {
                $this->productModel->syncRelatedProducts($productId, $data['related_products']);
            }
            
            $this->renderApiJson(['success' => true, 'message' => 'Product created successfully!', 'productId' => $productId], 201);
        } catch (Exception $e) {
            error_log("Error creating product: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Update an existing product.
     * PUT /api/admin/products/{id}
     * @param int $id
     */
    public function update(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }
        
        $data = $this->getPostData();
        $files = $_FILES;
        $existingProduct = $this->productModel->findById($id);
        
        if (!$existingProduct) {
            $this->renderApiJson(['success' => false, 'message' => 'Product not found.'], 404);
            return;
        }
        
        // Validation rules based on the provided database schema
        $rules = [
            'title' => 'required|max:255',
            'slug' => 'required|max:255|unique:products,slug,' . $id,
            'price' => 'required|numeric|min:0',
            'key_count' => 'required|integer|min:0',
            'stock_status' => 'required|in:in_stock,out_of_stock,pre_order',
            'release_date' => 'required|date',
            'developer_id' => 'required|integer',
            'publisher_id' => 'required|integer',
            'short_description' => 'required',
            'long_description' => 'required',
            'status' => 'required|in:draft,published,archived',
            'sku' => 'nullable|max:100|unique:products,sku,' . $id,
            'video_trailer' => 'nullable|max:255',
            'age_rating' => 'nullable|in:E,E10+,T,M,AO,RP',
            'file_size' => 'nullable|max:50',
            'meta_title' => 'nullable|max:255',
            'meta_description' => 'nullable',
        ];
        
        if (isset($files['featured_image']) && $files['featured_image']['error'] === UPLOAD_ERR_OK) {
            $rules['featured_image'] = 'required|file:image';
        }
        
        $validationData = array_merge($data, $files);
        if (!$this->validator->validate($validationData, $rules)) {
            $this->renderApiJson(['success' => false, 'message' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }
        
        try {
            $featuredImagePath = $existingProduct['featured_image'];
            if (isset($files['featured_image']) && $files['featured_image']['error'] === UPLOAD_ERR_OK) {
                if ($featuredImagePath) { 
                    $this->uploadService->deleteFile($featuredImagePath); 
                }
                $featuredImagePath = $this->uploadService->uploadFile($files['featured_image'], 'products/featured');
            }
            
            $existingGallery = isset($existingProduct['gallery']) && is_string($existingProduct['gallery']) ? json_decode($existingProduct['gallery'], true) : [];
            
            // Process image updates
            $galleryPaths = [];
            // Keep existing images that are marked to be kept
            $existingImagePathsToKeep = isset($data['existing_images']) && is_array($data['existing_images']) ? $data['existing_images'] : [];
            foreach ($existingGallery as $path) {
                if (in_array($path, $existingImagePathsToKeep)) {
                    $galleryPaths[] = $path;
                } else {
                   $this->uploadService->deleteFile($path);
                }
            }
            
            // Add new images if uploaded
            if (isset($files['images']) && $files['images']['error'][0] === UPLOAD_ERR_OK) {
                $newGalleryPaths = $this->uploadService->uploadMultipleFiles($files['images'], 'products/gallery');
                $galleryPaths = array_merge($galleryPaths, $newGalleryPaths);
            }
            
            $price = (float) $data['price'];
            
            // Handle sale_price with proper validation
            $salePrice = $existingProduct['sale_price']; // Keep existing by default
            if (isset($data['sale_price']) && is_numeric($data['sale_price'])) {
                $newSalePrice = (float) $data['sale_price'];
                // Ensure sale_price is less than price
                if ($newSalePrice < $price) {
                    $salePrice = $newSalePrice;
                } else {
                    $salePrice = null; // Set to null if not valid
                }
            }
            
            $discountPercentage = 0;
            if ($salePrice !== null && $price > 0) {
                $discountPercentage = round((($price - $salePrice) / $price) * 100, 2);
            }
            
            // Process system requirements to combine minimum and recommended
            // Fix: Decode JSON strings for min_requirements and rec_requirements
            $minRequirements = isset($data['min_requirements']) ? json_decode($data['min_requirements'], true) : [];
            $recRequirements = isset($data['rec_requirements']) ? json_decode($data['rec_requirements'], true) : [];
            
            // Combine minimum and recommended requirements into a single JSON structure
            $systemRequirements = [
                'minimum' => $minRequirements,
                'recommended' => $recRequirements
            ];
            
            // Prepare product data for database update
            $productData = [
                'title' => $data['title'],
                'slug' => $data['slug'],
                'short_description' => $data['short_description'],
                'description' => $data['long_description'],
                'price' => $price,
                'sale_price' => $salePrice,
                'discount_percentage' => $discountPercentage,
                'key_count' => (int) $data['key_count'],
                'stock_status' => $data['stock_status'],
                'featured_image' => $featuredImagePath,
                'gallery' => json_encode($galleryPaths),
                'release_date' => $data['release_date'],
                'publisher_id' => (int) $data['publisher_id'],
                'developer_id' => (int) $data['developer_id'],
                'system_requirements' => json_encode($systemRequirements),
                'is_featured' => isset($data['is_featured']) ? 1 : 0,
                'is_trending' => isset($data['is_trending']) ? 1 : 0,
                'status' => $data['status'],
                'sku' => $data['sku'] ?? $existingProduct['sku'],
                'video_trailer' => $data['video_trailer'] ?? $existingProduct['video_trailer'],
                'age_rating' => $data['age_rating'] ?? $existingProduct['age_rating'],
                'file_size' => $data['file_size'] ?? $existingProduct['file_size'],
                'meta_title' => $data['meta_title'] ?? $existingProduct['meta_title'],
                'meta_description' => $data['meta_description'] ?? $existingProduct['meta_description'],
            ];
            
            if (!$this->productModel->update($id, $productData)) {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to update product.'], 500);
                return;
            }
            
            // Update key count separately to ensure it's properly set
            $this->productModel->updateKeyCount($id, (int) $data['key_count']);
            
            // Sync relationships (categories, platforms, tags)
            $categories = isset($data['categories']) ? (is_array($data['categories']) ? $data['categories'] : explode(',', $data['categories'])) : [];
            $platforms = isset($data['platforms']) ? (is_array($data['platforms']) ? $data['platforms'] : explode(',', $data['platforms'])) : [];
            $tags = isset($data['tags']) ? (is_array($data['tags']) ? $data['tags'] : explode(',', $data['tags'])) : [];
            
            $this->productModel->syncRelationships($id, 'categories', $categories);
            $this->productModel->syncRelationships($id, 'platforms', $platforms);
            $this->productModel->syncRelationships($id, 'tags', $tags);
            
            // Update activation keys
            if (isset($data['keys']) && is_string($data['keys'])) {
                $keys = json_decode($data['keys'], true);
                if (is_array($keys)) {
                    $this->productModel->updateKeys($id, $keys);
                }
            }
            
            // Update related products if provided
            if (isset($data['related_products'])) {
                $relatedProducts = is_array($data['related_products']) ? $data['related_products'] : json_decode($data['related_products'], true);
                if (is_array($relatedProducts)) {
                    $this->productModel->syncRelatedProducts($id, $relatedProducts);
                }
            }
            
            $this->renderApiJson(['success' => true, 'message' => 'Product updated successfully!']);
        } catch (Exception $e) {
            error_log("Error updating product: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Delete a product.
     * DELETE /api/admin/products/{id}
     * @param int $id
     */
    public function destroy(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }
        
        $product = $this->productModel->findById($id);
        if (!$product) {
            $this->renderApiJson(['success' => false, 'message' => 'Product not found.'], 404);
            return;
        }
        
        try {
            if ($product['featured_image']) {
                $this->uploadService->deleteFile($product['featured_image']);
            }
            
            $galleryImages = isset($product['gallery']) && is_string($product['gallery']) ? json_decode($product['gallery'], true) : [];
            if (!empty($galleryImages)) {
                foreach ($galleryImages as $image) {
                    $this->uploadService->deleteFile($image);
                }
            }
            
            if ($this->productModel->delete($id)) {
                $this->renderApiJson(['success' => true, 'message' => 'Product deleted successfully!']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to delete product.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error deleting product: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Get all related data (categories, platforms, etc.) for product forms.
     * GET /api/admin/products/form-data
     */
    public function getFormData(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }
        
        try {
            $data = [
                'categories' => $this->categoryModel->getAll(),
                'platforms' => $this->platformModel->getAll(),
                'developers' => $this->developerModel->getAll(),
                'publishers' => $this->publisherModel->getAll(),
                'tags' => $this->tagModel->getAll(),
            ];
            
            $this->renderApiJson(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            error_log("Error fetching form data: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Retrieves POST data, which can be from a standard form or a JSON body.
     */
    private function getPostData(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }
        
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
}