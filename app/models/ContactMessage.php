<?php
namespace App\Models;
use App\Core\Model;
use PDO;
use PDOException;
class ContactMessage extends Model
{
    protected string $table = 'contact_messages';
    
    /**
     * Creates a new contact message record.
     *
     * @param array $data
     * @return int|false
     */
    public function createMessage(array $data): int|false
    {
        // The `create` method from the base Model class can be used directly
        // It handles sanitization and insertion.
        return $this->create($data);
    }
    
    /**
     * Fetches the total number of new contact messages.
     * @return int The total count of new messages.
     */
    public function countNew(): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = 'new'");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting new messages: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Fetches all contact messages, ordered by creation date descending.
     * @return array An array of contact messages.
     */
    public function getAll(): array
    {
        try {
            $query = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all contact messages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Updates the status of a specific contact message.
     * @param int $id The ID of the message.
     * @param string $status The new status.
     * @return bool True on success, false on failure.
     */
    public function updateStatus(int $id, string $status): bool
    {
        try {
            // A simple validation for the status field
            if (!in_array($status, ['new', 'in_progress', 'resolved', 'closed'])) {
                error_log("Invalid status provided for message ID {$id}: {$status}");
                return false;
            }
            return $this->update($id, ['status' => $status]);
        } catch (PDOException $e) {
            error_log("Error updating status for message ID {$id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Updates the priority of a specific contact message.
     * @param int $id The ID of the message.
     * @param string $priority The new priority.
     * @return bool True on success, false on failure.
     */
    public function updatePriority(int $id, string $priority): bool
    {
        try {
            // A simple validation for the priority field
            if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
                error_log("Invalid priority provided for message ID {$id}: {$priority}");
                return false;
            }
            return $this->update($id, ['priority' => $priority]);
        } catch (PDOException $e) {
            error_log("Error updating priority for message ID {$id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Assigns a message to a user.
     * @param int $id The ID of the message.
     * @param int|null $userId The ID of the user to assign to, or null to unassign.
     * @return bool True on success, false on failure.
     */
    public function assignTo(int $id, ?int $userId): bool
    {
        try {
            return $this->update($id, ['assigned_to' => $userId]);
        } catch (PDOException $e) {
            error_log("Error assigning message ID {$id} to user ID {$userId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marks a message as replied.
     * @param int $id The ID of the message.
     * @return bool True on success, false on failure.
     */
    public function markAsReplied(int $id): bool
    {
        try {
            return $this->update($id, ['replied_at' => date('Y-m-d H:i:s')]);
        } catch (PDOException $e) {
            error_log("Error marking message ID {$id} as replied: " . $e->getMessage());
            return false;
        }
    }
}