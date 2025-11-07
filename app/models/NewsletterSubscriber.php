<?php
namespace App\Models;
use App\Core\Model;
use PDO;
use PDOException;

class NewsletterSubscriber extends Model {
    protected string $table = 'newsletter_subscribers';
    protected array $fillable = ['email', 'name', 'status', 'preferences', 'subscribed_at', 'unsubscribed_at'];

    /**
     * Finds a subscriber by email address.
     *
     * @param string $email
     * @return array|false
     */
    public function findByEmail(string $email): array|false
    {
        return $this->first(['email' => $email]);
    }

    /**
     * Subscribes a new email address to the newsletter.
     *
     * @param string $email
     * @param string|null $name
     * @param array|null $preferences
     * @return bool
     */
    public function subscribe(string $email, ?string $name = null, ?array $preferences = null): bool
    {
        $existingSubscriber = $this->findByEmail($email);

        if ($existingSubscriber) {
            // Subscriber already exists, update their status if necessary
            if ($existingSubscriber['status'] !== 'active') {
                $updateData = [
                    'status' => 'active',
                    'unsubscribed_at' => null
                ];
                
                if ($name) $updateData['name'] = $name;
                if ($preferences) $updateData['preferences'] = json_encode($preferences);
                
                return $this->update($existingSubscriber[$this->primaryKey], $updateData);
            }
            // Already subscribed, so return true
            return true;
        }

        // Create a new subscriber
        $data = [
            'email' => $email,
            'status' => 'active',
            'subscribed_at' => date('Y-m-d H:i:s')
        ];
        
        if ($name) $data['name'] = $name;
        if ($preferences) $data['preferences'] = json_encode($preferences);

        return (bool) $this->create($data);
    }

    /**
     * Unsubscribe a user from the newsletter
     *
     * @param string $email
     * @return bool
     */
    public function unsubscribe(string $email): bool
    {
        $subscriber = $this->findByEmail($email);
        
        if (!$subscriber) {
            return false;
        }
        
        return $this->update($subscriber[$this->primaryKey], [
            'status' => 'unsubscribed',
            'unsubscribed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Mark a subscriber as bounced
     *
     * @param string $email
     * @return bool
     */
    public function markAsBounced(string $email): bool
    {
        $subscriber = $this->findByEmail($email);
        
        if (!$subscriber) {
            return false;
        }
        
        return $this->update($subscriber[$this->primaryKey], [
            'status' => 'bounced'
        ]);
    }

    /**
     * Fetches all newsletter subscribers with optional filtering
     * 
     * @param array $filters
     * @return array
     */
    public function getAll(array $filters = []): array
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
            $sql .= " ORDER BY subscribed_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching all newsletter subscribers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get active subscribers count
     * 
     * @return int
     */
    public function getActiveSubscribersCount(): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = 'active'");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting active subscribers: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get subscribers with specific preferences
     * 
     * @param array $preferences
     * @return array
     */
    public function getSubscribersByPreferences(array $preferences): array
    {
        try {
            $where = [];
            $params = [];
            
            foreach ($preferences as $key => $value) {
                $where[] = "JSON_CONTAIN(preferences, :{$key}, '$.{$key}')";
                $params[":{$key}"] = json_encode($value);
            }
            
            $sql = "SELECT * FROM {$this->table} WHERE status = 'active'";
            if (!empty($where)) {
                $sql .= " AND " . implode(' AND ', $where);
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching subscribers by preferences: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Deletes a specific newsletter subscriber.
     * @param int $id The ID of the subscriber.
     * @return bool True on success, false on failure.
     */
    public function delete($id): bool
    {
        return parent::delete($id);
    }
}