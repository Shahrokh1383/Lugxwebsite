<?php
namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class EmailCampaign extends Model
{
    protected string $table = 'email_campaigns';
    protected array $fillable = [
        'name', 'subject', 'content', 'sender_name', 'sender_email', 
        'status', 'scheduled_at', 'sent_at', 'recipients_count', 
        'opened_count', 'clicked_count'
    ];

    /**
     * Get all campaigns with optional filtering
     * 
     * @param array $filters
     * @return array
     */
    public function getAllCampaigns(array $filters = []): array
    {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filters['status'])) {
                $where[] = "status = :status";
                $params[':status'] = $filters['status'];
            }
            
            $sql = "SELECT * FROM {$this->table}";
            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching email campaigns: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a specific campaign by ID
     * 
     * @param int $id
     * @return array|false
     */
    public function getCampaignById(int $id): array|false
    {
        try {
            return $this->find($id);
        } catch (PDOException $e) {
            error_log("Error fetching campaign by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new email campaign
     * 
     * @param array $data
     * @return int|false
     */
    public function createCampaign(array $data): int|false
    {
        try {
            return $this->create($data);
        } catch (PDOException $e) {
            error_log("Error creating email campaign: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing campaign
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateCampaign(int $id, array $data): bool
    {
        try {
            return $this->update($id, $data);
        } catch (PDOException $e) {
            error_log("Error updating email campaign: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a campaign
     * 
     * @param int $id
     * @return bool
     */
    public function deleteCampaign(int $id): bool
    {
        try {
            return $this->delete($id);
        } catch (PDOException $e) {
            error_log("Error deleting email campaign: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update campaign statistics
     * 
     * @param int $id
     * @param array $stats
     * @return bool
     */
    public function updateStats(int $id, array $stats): bool
    {
        try {
            $set = [];
            $params = [];
            
            foreach ($stats as $key => $value) {
                $set[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
            
            $params[':id'] = $id;
            $setClause = implode(', ', $set);
            
            $sql = "UPDATE {$this->table} SET {$setClause} WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating campaign stats: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get campaigns ready to be sent (scheduled with time passed)
     * 
     * @return array
     */
    public function getReadyToSendCampaigns(): array
    {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE status = 'scheduled' 
                    AND scheduled_at <= NOW() 
                    ORDER BY scheduled_at ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching ready-to-send campaigns: " . $e->getMessage());
            return [];
        }
    }
}