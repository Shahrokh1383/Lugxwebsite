<?php
namespace App\Models;
use App\Core\Model;
use PDO;
use PDOException;
use Exception;

/**
 * PageView Model
 * Handles database operations for page views.
 */
class PageView extends Model
{
    protected string $table = 'page_views';
    
    /**
     * Creates a new page view entry.
     * @param array $data Associative array of page view data.
     * @return int|false Inserted ID on success, false on failure.
     */
    public function create(array $data): int|false
    {
        $query = "INSERT INTO {$this->table} 
                 (url, user_id, session_id, ip_address, user_agent, referer, device_type, browser, os, country, city) 
                 VALUES 
                 (:url, :user_id, :session_id, :ip_address, :user_agent, :referer, :device_type, :browser, :os, :country, :city)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':url', $data['url']);
            $stmt->bindValue(':user_id', $data['user_id'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':session_id', $data['session_id'] ?? null);
            $stmt->bindValue(':ip_address', $data['ip_address'] ?? null);
            $stmt->bindValue(':user_agent', $data['user_agent'] ?? null);
            $stmt->bindValue(':referer', $data['referer'] ?? null);
            $stmt->bindValue(':device_type', $data['device_type'] ?? 'desktop');
            $stmt->bindValue(':browser', $data['browser'] ?? null);
            $stmt->bindValue(':os', $data['os'] ?? null);
            $stmt->bindValue(':country', $data['country'] ?? null);
            $stmt->bindValue(':city', $data['city'] ?? null);
            
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating page view: " . $e->getMessage());
            return false;
        }   
    }
    
    /**
     * Fetches page views with optional filtering.
     * @param array $filters Optional filters (url, date_from, date_to, device_type).
     * @param int $limit Number of records to fetch.
     * @param int $offset Number of records to skip.
     * @return array List of page views.
     */
    public function getPageViews(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $query = "
                SELECT pv.*, u.first_name, u.last_name, u.email 
                FROM {$this->table} pv 
                LEFT JOIN users u ON pv.user_id = u.id
            ";
            $params = [];
            $whereClauses = [];
            
            if (!empty($filters['url'])) {
                $whereClauses[] = "pv.url LIKE :url";
                $params[':url'] = '%' . $filters['url'] . '%';
            }
            
            if (!empty($filters['date_from'])) {
                $whereClauses[] = "pv.created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $whereClauses[] = "pv.created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }
            
            if (!empty($filters['device_type'])) {
                $whereClauses[] = "pv.device_type = :device_type";
                $params[':device_type'] = $filters['device_type'];
            }
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(' AND ', $whereClauses);
            }
            
            $query .= " ORDER BY pv.created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching page views: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Counts page views with optional filtering.
     * @param array $filters Optional filters.
     * @return int Number of page views.
     */
    public function countPageViews(array $filters = []): int
    {
        try {
            $query = "SELECT COUNT(*) FROM {$this->table}";
            $params = [];
            $whereClauses = [];
            
            if (!empty($filters['url'])) {
                $whereClauses[] = "url LIKE :url";
                $params[':url'] = '%' . $filters['url'] . '%';
            }
            
            if (!empty($filters['date_from'])) {
                $whereClauses[] = "created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $whereClauses[] = "created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }
            
            if (!empty($filters['device_type'])) {
                $whereClauses[] = "device_type = :device_type";
                $params[':device_type'] = $filters['device_type'];
            }
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(' AND ', $whereClauses);
            }
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting page views: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Gets page view statistics grouped by URL.
     * @param string $dateFrom Start date.
     * @param string $dateTo End date.
     * @param int $limit Number of records to fetch.
     * @return array Page view statistics.
     */
    public function getTopPages(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        try {
            $query = "
                SELECT url, COUNT(*) as views, COUNT(DISTINCT user_id) as unique_visitors 
                FROM {$this->table} 
                WHERE created_at BETWEEN :date_from AND :date_to
                GROUP BY url 
                ORDER BY views DESC 
                LIMIT :limit
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':date_from', $dateFrom . ' 00:00:00');
            $stmt->bindValue(':date_to', $dateTo . ' 23:59:59');
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching top pages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Gets page view statistics grouped by device type.
     * @param string $dateFrom Start date.
     * @param string $dateTo End date.
     * @return array Device type statistics.
     */
    public function getDeviceStats(string $dateFrom, string $dateTo): array
    {
        try {
            $query = "
                SELECT device_type, COUNT(*) as views, COUNT(DISTINCT user_id) as unique_visitors 
                FROM {$this->table} 
                WHERE created_at BETWEEN :date_from AND :date_to
                GROUP BY device_type 
                ORDER BY views DESC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':date_from', $dateFrom . ' 00:00:00');
            $stmt->bindValue(':date_to', $dateTo . ' 23:59:59');
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching device stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Gets daily page view statistics.
     * @param string $dateFrom Start date.
     * @param string $dateTo End date.
     * @return array Daily page view statistics.
     */
    public function getDailyStats(string $dateFrom, string $dateTo): array
    {
        try {
            $query = "
                SELECT DATE(created_at) as date, COUNT(*) as views, COUNT(DISTINCT user_id) as unique_visitors 
                FROM {$this->table} 
                WHERE created_at BETWEEN :date_from AND :date_to
                GROUP BY DATE(created_at) 
                ORDER BY date ASC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':date_from', $dateFrom . ' 00:00:00');
            $stmt->bindValue(':date_to', $dateTo . ' 23:59:59');
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching daily stats: " . $e->getMessage());
            return [];
        }
    }
}