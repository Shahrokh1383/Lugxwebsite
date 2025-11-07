<?php
namespace App\Models;
use App\Core\Model;
class ReviewReply extends Model
{
    protected string $table = 'review_replies';
    protected string $primaryKey = 'id';
    
    public function createReply(array $data): int|false
    {
        return $this->create($data);
    }
    
    public function getRepliesByReviewId(int $reviewId): array
    {
        $sql = "SELECT rr.*, u.username 
                FROM {$this->table} rr
                LEFT JOIN users u ON rr.user_id = u.id
                WHERE rr.review_id = :review_id
                ORDER BY rr.created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':review_id', $reviewId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Update an existing reply
     */
    public function updateReply(int $replyId, string $replyText): bool
    {
        return $this->update($replyId, ['reply' => $replyText]);
    }
    
    /**
     * Delete a reply
     */
    public function deleteReply(int $replyId): bool
    {
        return $this->delete($replyId);
    }
    
    /**
     * Get admin reply for a review
     */
    public function getAdminReplyByReviewId(int $reviewId): array|false
    {
        $sql = "SELECT rr.*, u.username 
                FROM {$this->table} rr
                LEFT JOIN users u ON rr.user_id = u.id
                WHERE rr.review_id = :review_id AND rr.is_admin_reply = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':review_id', $reviewId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }
}