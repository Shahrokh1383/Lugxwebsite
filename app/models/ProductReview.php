<?php
namespace App\Models;
use App\Core\Model;
class ProductReview extends Model
{
    protected string $table = 'product_reviews';
    protected string $primaryKey = 'id';
    
    public function createReview(array $data): int|false
    {
        return $this->create($data);
    }
    
    public function findByProductId(int $productId, int $page = 1, int $perPage = 10, string $sortBy = 'created_at', string $order = 'DESC'): array
    {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT pr.*, u.username 
                FROM {$this->table} pr
                LEFT JOIN users u ON pr.user_id = u.id
                WHERE pr.product_id = :product_id AND pr.is_approved = 1
                ORDER BY {$sortBy} {$order}
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function countApprovedReviews(int $productId): int
    {
        $sql = "SELECT COUNT(*) as total 
                FROM {$this->table} 
                WHERE product_id = :product_id AND is_approved = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        return (int) $result['total'];
    }
    
    public function updateStatus(int $reviewId, bool $isApproved): bool
    {
        return $this->update($reviewId, ['is_approved' => $isApproved]);
    }
    
    public function getAverageRatingAndCount(int $productId): array
    {
        $sql = "SELECT 
                    AVG(rating) as average_rating,
                    COUNT(*) as review_count
                FROM {$this->table}
                WHERE product_id = :product_id AND is_approved = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        return [
            'average_rating' => round($result['average_rating'] ?? 0, 2),
            'review_count' => (int) ($result['review_count'] ?? 0)
        ];
    }
    
    public function findByUserAndProduct(int $userId, int $productId): array|false
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = :user_id AND product_id = :product_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    public function hasUserReviewedProduct(int $userId, int $productId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = :user_id AND product_id = :product_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    public function getReviewById(int $reviewId): array|false
    {
        $sql = "SELECT pr.*, u.username, p.title as product_name, p.featured_image as product_image
                FROM {$this->table} pr
                LEFT JOIN users u ON pr.user_id = u.id
                LEFT JOIN products p ON pr.product_id = p.id
                WHERE pr.id = :review_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':review_id', $reviewId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    public function updateHelpfulCount(int $reviewId, int $count): bool
    {
        return $this->update($reviewId, ['helpful_count' => $count]);
    }
    
    /**
     * Get all reviews with user and product information for admin panel
     * Enhanced with pagination, filtering and sorting
     */
    public function getAllWithUserInfoAndProductName(int $page = 1, int $perPage = 10, array $filters = [], string $sortBy = 'created_at', string $order = 'DESC'): array
    {
        $offset = ($page - 1) * $perPage;
        $whereConditions = [];
        $params = [];
        
        // Build WHERE conditions based on filters
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'approved') {
                $whereConditions[] = "pr.is_approved = 1";
            } elseif ($filters['status'] === 'pending') {
                $whereConditions[] = "pr.is_approved = 0";
            }
        }
        
        if (!empty($filters['rating'])) {
            $whereConditions[] = "pr.rating = :rating";
            $params[':rating'] = $filters['rating'];
        }
        
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereConditions[] = "(u.username LIKE :search OR p.title LIKE :search OR pr.title LIKE :search OR pr.review LIKE :search)";
            $params[':search'] = $searchTerm;
        }
        
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        }
        
        // Validate sort column to prevent SQL injection
        $allowedSortColumns = ['id', 'rating', 'created_at', 'username', 'product_name'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        
        $sql = "SELECT pr.*, u.username, p.title as product_name, p.featured_image as product_image
            FROM {$this->table} pr
            LEFT JOIN users u ON pr.user_id = u.id
            LEFT JOIN products p ON pr.product_id = p.id
            {$whereClause}
            ORDER BY {$sortBy} {$order}
            LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Count total reviews for pagination
     */
    public function countAllReviews(array $filters = []): int
    {
        $whereConditions = [];
        $params = [];
        
        // Build WHERE conditions based on filters
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'approved') {
                $whereConditions[] = "pr.is_approved = 1";
            } elseif ($filters['status'] === 'pending') {
                $whereConditions[] = "pr.is_approved = 0";
            }
        }
        
        if (!empty($filters['rating'])) {
            $whereConditions[] = "pr.rating = :rating";
            $params[':rating'] = $filters['rating'];
        }
        
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereConditions[] = "(u.username LIKE :search OR p.title LIKE :search OR pr.title LIKE :search OR pr.review LIKE :search)";
            $params[':search'] = $searchTerm;
        }
        
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        }
        
        $sql = "SELECT COUNT(*) as total
            FROM {$this->table} pr
            LEFT JOIN users u ON pr.user_id = u.id
            LEFT JOIN products p ON pr.product_id = p.id
            {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return (int) $result['total'];
    }
    
    /**
     * Get review statistics for admin dashboard
     */
    public function getReviewStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_reviews,
                    SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved_reviews,
                    SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending_reviews,
                    AVG(rating) as average_rating
                FROM {$this->table}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'total_reviews' => (int) $result['total_reviews'],
            'approved_reviews' => (int) $result['approved_reviews'],
            'pending_reviews' => (int) $result['pending_reviews'],
            'average_rating' => round($result['average_rating'] ?? 0, 2)
        ];
    }
}