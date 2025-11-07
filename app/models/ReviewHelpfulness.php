<?php
namespace App\Models;

use App\Core\Model;

class ReviewHelpfulness extends Model
{
    protected string $table = 'review_helpfulness';
    protected string $primaryKey = 'id';

    public function addHelpfulness(array $data): int|false
    {
        return $this->create($data);
    }

    public function removeHelpfulness(int $reviewId, int $userId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE review_id = :review_id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':review_id', $reviewId, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getUserVote(int $reviewId, int $userId): array|false
    {
        $sql = "SELECT * FROM {$this->table} WHERE review_id = :review_id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':review_id', $reviewId, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function countVotes(int $reviewId): array
    {
        $sql = "SELECT 
                    SUM(CASE WHEN is_helpful = 1 THEN 1 ELSE 0 END) as helpful,
                    SUM(CASE WHEN is_helpful = 0 THEN 1 ELSE 0 END) as unhelpful
                FROM {$this->table}
                WHERE review_id = :review_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':review_id', $reviewId, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        return [
            'helpful' => (int) ($result['helpful'] ?? 0),
            'unhelpful' => (int) ($result['unhelpful'] ?? 0)
        ];
    }
}