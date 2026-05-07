<?php
/*
 * app/models/ClubMember.php
 * --------------------------
 * Thin model for the club_members join table.
 * The main Club model handles most membership logic.
 * This model adds helpers for role management within a club.
 */

class ClubMember
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get a single membership record for a user in a club.
     *
     * @param int $clubId
     * @param int $userId
     * @return array|false
     */
    public function get(int $clubId, int $userId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM club_members WHERE club_id = :cid AND user_id = :uid"
        );
        $stmt->execute([':cid' => $clubId, ':uid' => $userId]);
        return $stmt->fetch();
    }

    /**
     * Remove a user from a club.
     *
     * @param int $clubId
     * @param int $userId
     * @return bool
     */
    public function remove(int $clubId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM club_members WHERE club_id = :cid AND user_id = :uid"
        );
        return $stmt->execute([':cid' => $clubId, ':uid' => $userId]);
    }
}
