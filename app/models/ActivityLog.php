<?php
namespace App\Models;
use App\Core\Model;
use PDO;
use PDOException;
use Exception;

/**
 * ActivityLog Model
 * Handles database operations for activity logs.
 */
class ActivityLog extends Model
{
    protected string $table = 'activity_logs';
    
    /**
     * Creates a new activity log entry.
     * @param array $data Associative array of log data.
     * @return int|false Inserted ID on success, false on failure.
     */
    public function create(array $data): int|false
    {
        $query = "INSERT INTO {$this->table} 
                 (user_id, action, description, model_type, model_id, old_values, new_values, ip_address, user_agent) 
                 VALUES 
                 (:user_id, :action, :description, :model_type, :model_id, :old_values, :new_values, :ip_address, :user_agent)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $data['user_id'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':action', $data['action']);
            $stmt->bindValue(':description', $data['description'] ?? null);
            $stmt->bindValue(':model_type', $data['model_type'] ?? null);
            $stmt->bindValue(':model_id', $data['model_id'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':old_values', isset($data['old_values']) ? json_encode($data['old_values']) : null);
            $stmt->bindValue(':new_values', isset($data['new_values']) ? json_encode($data['new_values']) : null);
            $stmt->bindValue(':ip_address', $data['ip_address'] ?? null);
            $stmt->bindValue(':user_agent', $data['user_agent'] ?? null);
            
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating activity log: " . $e->getMessage());
            return false;
        }   
    }
    
    /**
     * Fetches activity logs with optional filtering.
     * @param array $filters Optional filters (user_id, action, model_type, date_from, date_to).
     * @param int $limit Number of records to fetch.
     * @param int $offset Number of records to skip.
     * @return array List of activity logs.
     */
    public function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $query = "
                SELECT al.*, u.first_name, u.last_name, u.email 
                FROM {$this->table} al 
                LEFT JOIN users u ON al.user_id = u.id
            ";
            $params = [];
            $whereClauses = [];
            
            if (!empty($filters['user_id'])) {
                $whereClauses[] = "al.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['action'])) {
                $whereClauses[] = "al.action LIKE :action";
                $params[':action'] = '%' . $filters['action'] . '%';
            }
            
            if (!empty($filters['model_type'])) {
                $whereClauses[] = "al.model_type = :model_type";
                $params[':model_type'] = $filters['model_type'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereClauses[] = "al.created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $whereClauses[] = "al.created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(' AND ', $whereClauses);
            }
            
            $query .= " ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching activity logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Counts activity logs with optional filtering.
     * @param array $filters Optional filters.
     * @return int Number of logs.
     */
    public function countLogs(array $filters = []): int
    {
        try {
            $query = "SELECT COUNT(*) FROM {$this->table}";
            $params = [];
            $whereClauses = [];
            
            if (!empty($filters['user_id'])) {
                $whereClauses[] = "user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['action'])) {
                $whereClauses[] = "action LIKE :action";
                $params[':action'] = '%' . $filters['action'] . '%';
            }
            
            if (!empty($filters['model_type'])) {
                $whereClauses[] = "model_type = :model_type";
                $params[':model_type'] = $filters['model_type'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereClauses[] = "created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $whereClauses[] = "created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
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
            error_log("Error counting activity logs: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Adds a log entry.
     * @param string $action The action performed.
     * @param string|null $description Optional description.
     * @param string|null $modelType The model type.
     * @param int|null $modelId The model ID.
     * @param array|null $oldValues Old values before change.
     * @param array|null $newValues New values after change.
     * @param int|null $userId User ID who performed the action.
     * @return bool True on success, false on failure.
     */
    public static function addLog(
        string $action, 
        ?string $description = null, 
        ?string $modelType = null, 
        ?int $modelId = null, 
        ?array $oldValues = null, 
        ?array $newValues = null, 
        ?int $userId = null
    ): bool {
        try {
            $log = new self();
            
            $data = [
                'action' => $action,
                'description' => $description,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'user_id' => $userId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            
            return $log->create($data) > 0;
        } catch (Exception $e) {
            error_log("Error adding activity log: " . $e->getMessage());
            return false;
        }
    }
}