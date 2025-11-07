<?php
namespace App\Models;
use App\Core\Model;
use PDO;
use PDOException;
use Exception;
/**
 * Product Model
 * Handles database operations for products, including fetching, filtering, and searching.
 */
class Product extends Model
{
    protected string $table = 'products';
    public function __construct()
    {
        parent::__construct(); // Call the parent constructor to initialize $this->db
    }
    /**
     * Creates a new product in the 'products' table.
     * This method only handles the main product data. Relationships should be handled separately.
     *
     * @param array $data The data for the new product.
     * @return int|false The ID of the new product on success, or false on failure.
     */
    public function create(array $data): int|false
    {
        try {
            // Prepare and insert into the 'products' table
            $columns = array_keys($data);
            $placeholders = array_map(fn($col) => ":$col", $columns);
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->db->prepare($sql);
            foreach ($data as $column => $value) {
                $paramType = match (gettype($value)) {
                    'integer' => PDO::PARAM_INT,
                    'boolean' => PDO::PARAM_BOOL,
                    'NULL' => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };
                $stmt->bindValue(":$column", $value, $paramType);
            }
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert product data.");
            }
            return (int) $this->db->lastInsertId();
        } catch (Exception | PDOException $e) {
            error_log("Error creating product: " . $e->getMessage());
            return false;
        }
    }
    /**
     * @param int $id The ID of the product to update.
     * @param array $data The data to update.
     * @return bool True on success, false on failure.
     */
    public function update($id, array $data): bool
    {
        // Check if the data is empty
        if (empty($data)) {
            return true;
        }
        try {
            // Start a database transaction
            $this->db->beginTransaction();
            
            // Build the SET clause for the SQL query
            $setClauses = [];
            foreach ($data as $column => $value) {
                $setClauses[] = "{$column} = :{$column}";
            }
            $set = implode(', ', $setClauses);
            // Add the 'updated_at' timestamp
            $set .= ", updated_at = CURRENT_TIMESTAMP";
            // Prepare the SQL query
            $sql = "UPDATE {$this->table} SET {$set} WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            // Bind the ID parameter
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            // Bind the rest of the data parameters
            foreach ($data as $column => $value) {
                // Determine the parameter type based on the data type
                $paramType = match (gettype($value)) {
                    'integer' => PDO::PARAM_INT,
                    'boolean' => PDO::PARAM_BOOL,
                    'NULL'    => PDO::PARAM_NULL,
                    default   => PDO::PARAM_STR,
                };
                $stmt->bindParam(":$column", $data[$column], $paramType);
            }
            // Execute the query
            $success = $stmt->execute();
            
            if ($success) {
                $this->db->commit();
            } else {
                $this->db->rollBack();
            }
            return $success;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error updating product: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Fetches a single product by its ID.
     * @param int $id The ID of the product.
     * @return array|null Product data if found, null otherwise.
     */
    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($product) {
                // Decode JSON fields if they exist
                if (isset($product['gallery']) && is_string($product['gallery'])) {
                    $product['gallery'] = json_decode($product['gallery'], true);
                }
                if (isset($product['system_requirements']) && is_string($product['system_requirements'])) {
                    $systemRequirements = json_decode($product['system_requirements'], true);
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
                }
            }
            return $product ?: null;
        }catch (PDOException $e) {
            error_log("Error fetching product by ID: " . $e->getMessage());
            return null;
        }
    }
    /**
     * Fetches a single product by its slug.
     * @param string $slug The slug of the product.
     * @return array|null Product data if found, null otherwise.
     */
    public function findBySlug(string $slug): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE slug = :slug");
            $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                if (isset($product['gallery']) && is_string($product['gallery'])) {
                    $product['gallery'] = json_decode($product['gallery'], true);
                }
                if (isset($product['system_requirements']) && is_string($product['system_requirements'])) {
                    $systemRequirements = json_decode($product['system_requirements'], true);
                    // Ensure system_requirements is in the correct format
                    if (is_array(($systemRequirements))) {
                        $product['system_requirements'] = $systemRequirements;
                    }else {
                        $product['system_requirements'] = [
                            'minimum' => [],
                            'recommended' => []
                        ];
                    }
                }else {
                    $product['system_requirements'] = [
                        'minimum' => [],
                        'recommended' => []
                    ];
                }
            }
            return $product ?: null;
        }catch (PDOException $e) {
            error_log("Error fetching product by slug: " . $e->getMessage());
            return null;
        }
    }
    /**
     * Fetches essential product details for cart and order processing.
     * Includes price, sale_price, and available stock (key_count).
     *
     * @param int $productId The ID of the product.
     * @return array|null Product data if found, null otherwise.
     */
    public function getProductForCart(int $productId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT id, price, sale_price, key_count, stock_status, status FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching product for cart: " . $e->getMessage());
            return null;
        }
    }
    /**
     * Updates the stock (key_count) of a product.
     *
     * @param int $productId The ID of the product.
     * @param int $quantityChange The amount to change the stock by (positive for increase, negative for decrease).
     * @return bool True on success, false on failure.
     */
    public function updateStock(int $productId, int $quantityChange): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET key_count = key_count + :quantity_change, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->bindParam(':quantity_change', $quantityChange, PDO::PARAM_INT);
            $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating product stock: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Updates the average rating and review count for a product.
     * This method is typically called after a review is added, updated, or approved/unapproved.
     *
     * @param int $productId The ID of the product.
     * @param float $averageRating The new average rating.
     * @param int $reviewCount The new total count of approved reviews.
     * @return bool True on success, false on failure.
     */
    public function updateReviewStats(int $productId, float $averageRating, int $reviewCount): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET average_rating = :average_rating, reviews_count = :review_count, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->bindParam(':average_rating', $averageRating, PDO::PARAM_STR);
            $stmt->bindParam(':review_count', $reviewCount, PDO::PARAM_INT);
            $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
            $success = $stmt->execute();
            
            // Log the update for debugging
            if ($success) {
                error_log("Updated product stats for ID {$productId}: average_rating={$averageRating}, reviews_count={$reviewCount}");
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("Failed to update product stats for ID {$productId}: " . $errorInfo[2]);
            }
            
            return $success;
        }catch (PDOException $e) {
            error_log("Error updating product review stats for product ID {$productId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update product rating statistics
     * 
     * @param int $productId Product ID
     * @return bool Success status
     */
    public function updateRatingStats(int $productId): bool
    {
        $reviewModel = new ProductReview();
        $stats = $reviewModel->getAverageRatingAndCount($productId);
        return $this->update($productId, [
            'average_rating' => $stats['average_rating'],
            'reviews_count' => $stats['review_count']
        ]);
    }
    /**
     * Fetches a list of products with various filters, search, sorting, and pagination.
     * @param array $filters Associative array of filters (e.g., 'category_id', 'platform_id', 'search_query', 'price_min', 'price_max', 'is_featured', 'is_trending').
     * @param int $page Current page number.
     * @param int $limit Number of products per page.
     * @param string $sortBy Column to sort by (e.g., 'created_at', 'price', 'name', 'average_rating', 'release_date', 'sales_count', 'views_count', 'downloads_count').
     * @param string $sortOrder Sort order ('ASC' or 'DESC').
     * @return array An array containing 'products' (list of product data) and 'total_products' (total count).
     */
    public function getAll(
        array $filters = [],
        int $page = 1,
        int $limit = 10,
        string $sortBy = 'created_at',
        string $sortOrder = 'DESC'
    ): array {
        $products = [];
        $totalProducts = 0;
        // Base query for fetching products
        $query = "SELECT p.* FROM {$this->table} p";
        $countQuery = "SELECT COUNT(DISTINCT p.id) FROM {$this->table} p";
        $joinClauses = [];
        $whereClauses = ["p.status = 'published'"]; // Only fetch published products by default
        $params = [];
        // Build WHERE and JOIN clauses based on filters
        if (isset($filters['category_id']) && !empty($filters['category_id'])) {
            $joinClauses[] = "JOIN product_categories pc ON p.id = pc.product_id";
            $whereClauses[] = "pc.category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }
        if (isset($filters['platform_id']) && !empty($filters['platform_id'])) {
            $joinClauses[] = "JOIN product_platforms pp ON p.id = pp.product_id";
            $whereClauses[] = "pp.platform_id = :platform_id";
            $params[':platform_id'] = $filters['platform_id'];
        }
        if (isset($filters['tag_id']) && !empty($filters['tag_id'])) {
            $joinClauses[] = "JOIN product_tags pt ON p.id = pt.product_id";
            $whereClauses[] = "pt.tag_id = :tag_id";
            $params[':tag_id'] = $filters['tag_id'];
        }
        if (isset($filters['publisher_id']) && !empty($filters['publisher_id'])) {
            $whereClauses[] = "p.publisher_id = :publisher_id";
            $params[':publisher_id'] = $filters['publisher_id'];
        }
        if (isset($filters['developer_id']) && !empty($filters['developer_id'])) {
            $whereClauses[] = "p.developer_id = :developer_id";
            $params[':developer_id'] = $filters['developer_id'];
        }
        if (isset($filters['price_min']) && $filters['price_min'] !== '') {
            $whereClauses[] = "p.price >= :price_min";
            $params[':price_min'] = $filters['price_min'];
        }
        if (isset($filters['price_max']) && $filters['price_max'] !== '') {
            $whereClauses[] = "p.price <= :price_max";
            $params[':price_max'] = $filters['price_max'];
        }
        if (isset($filters['is_featured']) && $filters['is_featured'] !== '') {
            $whereClauses[] = "p.is_featured = :is_featured";
            $params[':is_featured'] = (bool)$filters['is_featured'];
        }
        if (isset($filters['is_trending']) && $filters['is_trending'] !== '') {
             $whereClauses[] = "p.is_trending = :is_trending";
             $params[':is_trending'] = (bool)$filters['is_trending'];
        }
        if (!empty($filters['search_query'])) {
            $searchQueryVal = '%' . $filters['search_query'] . '%';
            $whereClauses[] = "(p.title LIKE :search_query_title OR p.short_description LIKE :search_query_short_desc OR p.description LIKE :search_query_desc)";
            $params[':search_query_title'] = $searchQueryVal;
            $params[':search_query_short_desc'] = $searchQueryVal;
            $params[':search_query_desc'] = $searchQueryVal;
        }
        // Combine clauses
        $fullJoinClause = implode(' ', array_unique($joinClauses));
        $fullWhereClause = implode(' AND ', $whereClauses);
        
        // Ensure WHERE clause is prefixed correctly
        $finalWhereClause = !empty($fullWhereClause) ? " WHERE " . $fullWhereClause : "";
        // --- Get total count first ---
        try {
            // Ensure space before join clause if it exists
            $finalCountQuery = $countQuery;
            if (!empty($fullJoinClause)) {
                $finalCountQuery .= ' ' . $fullJoinClause;
            }
            $finalCountQuery .= $finalWhereClause;
            $countStmt = $this->db->prepare($finalCountQuery);
            foreach ($params as $key => $val) {
                // Bind all parameters, including the new unique search parameters
                $countStmt->bindValue($key, $val);
            }
            $countStmt->execute();
            $totalProducts = $countStmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting products: " . $e->getMessage());
            return ['products' => [], 'total_products' => 0];
        }
        // --- Now fetch the actual products ---
        // Validate sortBy column to prevent SQL injection
        $allowedSortBy = ['created_at', 'name', 'price', 'release_date', 'average_rating', 'sales_count', 'views_count', 'downloads_count'];
        if (!in_array($sortBy, $allowedSortBy)) {
            $sortBy = 'created_at'; // Default to a safe column
        }
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        // Ensure space before join clause if it exists for the main query as well
        $finalQuery = $query;
        if (!empty($fullJoinClause)) {
            $finalQuery .= ' ' . $fullJoinClause;
        }
        $finalQuery .= $finalWhereClause . " GROUP BY p.id ORDER BY p.{$sortBy} {$sortOrder} LIMIT :limit OFFSET :offset";
        
        $offset = ($page - 1) * $limit;
        try {
            $stmt = $this->db->prepare($finalQuery);
            foreach ($params as $key => $val) {
                // Bind all parameters, including the new unique search parameters
                $stmt->bindValue($key, $val);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Decode JSON fields for each product
            foreach ($products as &$product) {
                if (isset($product['gallery']) && is_string($product['gallery'])) {
                    $product['gallery'] = json_decode($product['gallery'], true);
                }
                if (isset($product['system_requirements']) && is_string($product['system_requirements'])) {
                    $product['system_requirements'] = json_decode($product['system_requirements'], true);
                }
            }
        } catch (PDOException $e) {
            error_log("Error fetching all products: " . $e->getMessage());
            return ['products' => [], 'total_products' => 0];
        }
        return ['products' => $products, 'total_products' => $totalProducts];
    }
    /**
     * Get categories associated with a product.
     * @param int $productId
     * @return array
     */
    public function getCategories(int $productId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT c.id, c.name, c.slug, pc.is_primary
                FROM product_categories pc
                JOIN categories c ON pc.category_id = c.id
                WHERE pc.product_id = :product_id
            ");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching product categories: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Get platforms associated with a product.
     * @param int $productId
     * @return array
     */
    public function getPlatforms(int $productId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT p.id, p.name, p.slug, p.icon
                FROM product_platforms pp
                JOIN platforms p ON pp.platform_id = p.id
                WHERE pp.product_id = :product_id
            ");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching product platforms: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Get tags associated with a product.
     * @param int $productId
     * @return array
     */
    public function getTags(int $productId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT t.id, t.name, t.slug, t.color
                FROM product_tags pt
                JOIN tags t ON pt.tag_id = t.id
                WHERE pt.product_id = :product_id
            ");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching product tags: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Get publisher details for a product.
     * @param int $publisherId
     * @return array|null
     */
    public function getPublisher(int $publisherId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT id, name, slug, logo, website FROM publishers WHERE id = :id");
            $stmt->bindParam(':id', $publisherId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching product publisher: " . $e->getMessage());
            return null;
        }
    }
    /**
     * Get developer details for a product.
     * @param int $developerId
     * @return array|null
     */
    public function getDeveloper(int $developerId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT id, name, slug, logo, website FROM developers WHERE id = :id");
            $stmt->bindParam(':id', $developerId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching product developer: " . $e->getMessage());
            return null;
        }
    }
    /**
     * Get related products for a given product.
     * @param int $productId
     * @param int $limit
     * @return array
     */
    public function getRelatedProducts(int $productId, int $limit = 4): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT p.id, p.title as name, p.slug, p.featured_image as main_image_url, p.price, p.sale_price, p.average_rating
                FROM related_products rp
                JOIN products p ON rp.related_product_id = p.id
                WHERE rp.product_id = :product_id AND p.status = 'published'
                LIMIT :limit
            ");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching related products: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetches the total number of products.
     * @return int The total count of products.
     */
    public function countAll(): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table}");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting all products: " . $e->getMessage());
            return 0;
        }
    }
    /**
     * Fetches the most recent products.
     * @param int $limit The number of recent products to fetch.
     * @return array An array of recent product data.
     */
    public function findLast(int $limit = 5): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT :limit");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Decode JSON fields for each product
            foreach ($products as &$product) {
                if (isset($product['gallery']) && is_string($product['gallery'])) {
                    $product['gallery'] = json_decode($product['gallery'], true);
                }
                if (isset($product['system_requirements']) && is_string($product['system_requirements'])) {
                    $product['system_requirements'] = json_decode($product['system_requirements'], true);
                }
            }
            unset($product); // Unset the reference
            return $products;
        } catch (PDOException $e) {
            error_log("Error fetching recent products: " . $e->getMessage());
            return [];
        }
    }
    // =========================================================================
    // NEW METHODS FOR ADMIN PANEL
    // =========================================================================
    /**
     * Fetches a list of all products with associated details for the admin panel.
     *
     * @return array An array of products with their relationships.
     */
    public function allWithDetails(): array
    {
        $products = [];
        try {
            $stmt = $this->db->query("SELECT p.*, d.name AS developer_name, u.name AS publisher_name FROM {$this->table} p LEFT JOIN developers d ON p.developer_id = d.id LEFT JOIN publishers u ON p.publisher_id = u.id ORDER BY p.id DESC");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Fetch and attach relationships for each product
            foreach ($products as &$product) {
                $product['categories'] = $this->getCategories($product['id']);
                $product['platforms'] = $this->getPlatforms($product['id']);
                $product['tags'] = $this->getTags($product['id']);
            }
            unset($product); // Unset the reference
            return $products;
        }catch (PDOException $e) {
            error_log("Error fetching all products with details: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Fetches a single product with all its associated details for the admin panel.
     *
     * @param int $id The ID of the product.
     * @return array|null Product data with relationships if found, null otherwise.
     */
    public function findByIdWithDetails(int $id): ?array
    {
        $product = $this->findById($id);
        if ($product) {
            $product['categories'] = $this->getCategories($id);
            $product['platforms'] = $this->getPlatforms($id);
            $product['tags'] = $this->getTags($id);
            if ($product['developer_id']) {
                $product['developer'] = $this->getDeveloper($product['developer_id']);
            }
            if ($product['publisher_id']) {
                $product['publisher'] = $this->getPublisher($product['publisher_id']);
            }
        }
        return $product;
    }
    /**
    * Syncs a product's relationships with a given list of related IDs.
    * This method handles Many-to-Many relationships by clearing existing ones and re-inserting the new ones.
    *
    * @param int $productId The ID of the product.
    * @param string $relation The name of the relationship ('categories', 'platforms', 'tags', 'related_products').
    * @param array $relatedIds An array of IDs to sync.
    * @param string $relationType Optional relation type for related_products ('similar', 'cross_sell', 'up_sell').
    * @return bool True on success, false on failure.
    */
    public function syncRelationships(int $productId, string $relation, array $relatedIds, string $relationType = 'similar'): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Handle related_products differently as it has a special structure
            if ($relation === 'related_products') {
                // Delete all existing related products
                $deleteStmt = $this->db->prepare("DELETE FROM related_products WHERE product_id = :product_id");
                $deleteStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
                $deleteStmt->execute();
                
                // Insert new related products if any
                if (!empty($relatedIds)) {
                    $values = [];
                    $params = [];
                    
                    foreach ($relatedIds as $id) {
                        $values[] = "(?, ?, ?)";
                        $params[] = $productId;
                        $params[] = (int) $id;
                        $params[] = $relationType;
                    }
                    
                    $sql = "INSERT INTO related_products (product_id, related_product_id, relation_type) VALUES " . implode(', ', $values);
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                }
            } else {
                // Handle standard many-to-many relationships (categories, platforms, tags)
                $joinTable = 'product_' . $relation;
                
                if ($relation === 'categories') {
                    $relatedForeignKey = 'category_id';
                } elseif ($relation === 'platforms') {
                    $relatedForeignKey = 'platform_id';
                } elseif ($relation === 'tags') {
                    $relatedForeignKey = 'tag_id';
                } else {
                    error_log("Invalid relationship name: {$relation}");
                    $this->db->rollBack();
                    return false;
                }
                
                // Delete all existing relationships
                $deleteStmt = $this->db->prepare("DELETE FROM {$joinTable} WHERE product_id = :product_id");
                $deleteStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
                $deleteStmt->execute();
                
                // Insert new relationships if any
                if (!empty($relatedIds)) {
                    $values = [];
                    $params = [];
                    
                    foreach ($relatedIds as $id) {
                        $values[] = "(?, ?)";
                        $params[] = $productId;
                        $params[] = (int) $id;
                    }
                    
                    $sql = "INSERT INTO {$joinTable} (product_id, {$relatedForeignKey}) VALUES " . implode(', ', $values);
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                }
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error syncing product relationships ({$relation}): " . $e->getMessage());
            return false;
        }
    }
    /**
    * Syncs related products for a given product.
    * This is a convenience method that calls syncRelationships with the correct parameters.
    *
    * @param int $productId The ID of the product.
    * @param array $relatedProducts An array of related product data with 'id' and optional 'relation_type'.
    * @return bool True on success, false on failure.
    */
    public function syncRelatedProducts(int $productId, array $relatedProducts): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Delete all existing related products
            $deleteStmt = $this->db->prepare("DELETE FROM related_products WHERE product_id = :product_id");
            $deleteStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $deleteStmt->execute();
            
            // Insert new related products if any
            if (!empty($relatedProducts)) {
                $values = [];
                $params = [];
                
                foreach ($relatedProducts as $product) {
                    if (isset($product['id'])) {
                        $relationType = $product['relation_type'] ?? 'similar';
                        $values[] = "(?, ?, ?)";
                        $params[] = $productId;
                        $params[] = (int) $product['id'];
                        $params[] = $relationType;
                    }
                }
                
                if (!empty($values)) {
                    $sql = "INSERT INTO related_products (product_id, related_product_id, relation_type) VALUES " . implode(', ', $values);
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                }
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error syncing related products: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Fetches a list of products for the admin panel with search and pagination.
     * This method is different from `getAll` as it does not filter by status='published'.
     *
     * @param int $limit Number of products to fetch.
     * @param int $offset Starting offset.
     * @param string $search Search query for product title.
     * @return array An array containing 'products' (list of product data) and 'total_products' (total count).
     */
    public function getAllForAdmin(int $limit, int $offset, string $search = ''): array
    {
        $products = [];
        $totalProducts = 0;
        $params = [];
        $whereClause = '';
        if (!empty($search)) {
            $whereClause = "WHERE p.title LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }
        try {
            // Get total count
            $countQuery = "SELECT COUNT(p.id) FROM {$this->table} p {$whereClause}";
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->execute($params);
            $totalProducts = (int) $countStmt->fetchColumn();
            // Fetch products
            $query = "SELECT p.*, d.name AS developer_name, u.name AS publisher_name FROM {$this->table} p LEFT JOIN developers d ON p.developer_id = d.id LEFT JOIN publishers u ON p.publisher_id = u.id {$whereClause} ORDER BY p.id DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->db->prepare($query);
            
            // Bind parameters
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Fetch and attach relationships for each product
            foreach ($products as &$product) {
                $product['categories'] = $this->getCategories($product['id']);
                $product['platforms'] = $this->getPlatforms($product['id']);
                $product['tags'] = $this->getTags($product['id']);
            }
            unset($product);
        } catch (PDOException $e) {
            error_log("Error fetching all products with details for admin: " . $e->getMessage());
            return ['products' => [], 'total_products' => 0];
        }
        return ['products' => $products, 'total_products' => $totalProducts];
    }
    /**
     * Adds new activation keys for a product.
     * Assumes a 'product_keys' table exists with columns: id, product_id, key_value, status, created_at
     *
     * @param int $productId The ID of the product.
     * @param array $keys An array of key strings to add.
     * @return bool True on success, false on failure.
     */
    public function addKeys(int $productId, array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }
        try {
            $this->db->beginTransaction();
            $query = "INSERT INTO product_keys (product_id, license_key, is_used) VALUES (?, ?, FALSE)";
            $stmt = $this->db->prepare($query);
            
            foreach ($keys as $key) {
                $stmt->execute([$productId, $key]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error adding product keys: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Fetches all activation keys for a given product.
     *
     * @param int $productId The ID of the product.
     * @return array An array of key data.
     */
    public function getActivationKeys(int $productId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT license_key FROM product_keys WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error fetching product keys: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Updates the activation keys for a product.
     * This method deletes all existing keys and inserts the new ones.
     *
     * @param int $productId The ID of the product.
     * @param array $keys An array of key strings to update.
     * @return bool True on success, false on failure.
     */
    public function updateKeys(int $productId, array $keys): bool
    {
        try {
            $this->db->beginTransaction();
            // 1. Delete all existing keys for the product
            $deleteStmt = $this->db->prepare("DELETE FROM product_keys WHERE product_id = :product_id");
            $deleteStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $deleteStmt->execute();
            
            // 2. Insert the new keys
            if (!empty($keys)) {
                $query = "INSERT INTO product_keys (product_id, license_key, is_used) VALUES (?, ?, FALSE)";
                $stmt = $this->db->prepare($query);
                
                foreach ($keys as $key) {
                    $stmt->execute([$productId, $key]);
                }
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error updating product keys: " . $e->getMessage());
            return false;
        }
    }
    /**
    * Updates the key count for a product.
    *
    * @param int $productId The ID of the product.
    * @param int $keyCount The new key count.
     * @return bool True on success, false on failure.
    */
    public function updateKeyCount(int $productId, int $keyCount): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET key_count = :key_count, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->bindParam(':key_count', $keyCount, PDO::PARAM_INT);
            $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating product key count: " . $e->getMessage());
            return false;
        }
    }
}