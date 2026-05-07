<?php
/*
 * app/models/Vote.php
 * --------------------
 * Thin model for the votes join table (F14 — Vote-for-Next Engine).
 * The Nomination model handles most voting logic.
 * This model provides a check to see if a user already voted.
 */

class Vote
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Check if a user has already voted on a specific nomination.
     *
     * @param int $nominationId
     * @param int $userId
     * @return bool
     */
    public function hasVoted(int $nominationId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM votes WHERE nomination_id = :nid AND user_id = :uid"
        );
        $stmt->execute([':nid' => $nominationId, ':uid' => $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
