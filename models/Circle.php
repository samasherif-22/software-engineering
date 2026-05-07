<?php
/*
 * app/models/Circle.php
 * ----------------------
 * Handles niche interest circles (F21).
 * Circles are micro-communities based on genre/interest tags.
 * If a circle for a tag doesn't exist yet, it is created automatically.
 */

class Circle
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all circles, with member counts.
     *
     * @return array
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, COUNT(cm.user_id) AS member_count
             FROM circles c
             LEFT JOIN circle_members cm ON cm.circle_id = c.id
             GROUP BY c.id ORDER BY member_count DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get a circle by its tag string, or return false if not found.
     *
     * @param string $tag
     * @return array|false
     */
    public function getByTag(string $tag)
    {
        $stmt = $this->db->prepare("SELECT * FROM circles WHERE tag = :tag");
        $stmt->execute([':tag' => $tag]);
        return $stmt->fetch();
    }

    /**
     * Create a new circle for a tag.
     *
     * @param string $tag
     * @return int  New circle ID
     */
    public function create(string $tag): int
    {
        $stmt = $this->db->prepare("INSERT INTO circles (tag) VALUES (:tag)");
        $stmt->execute([':tag' => $tag]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Add a user to a circle. IGNORE prevents duplicate membership errors.
     *
     * @param int $circleId
     * @param int $userId
     * @return bool
     */
    public function addMember(int $circleId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO circle_members (circle_id, user_id)
             VALUES (:cid, :uid)"
        );
        return $stmt->execute([':cid' => $circleId, ':uid' => $userId]);
    }

    /**
     * Get all circles a user has joined.
     *
     * @param int $userId
     * @return array
     */
    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.* FROM circles c
             JOIN circle_members cm ON cm.circle_id = c.id
             WHERE cm.user_id = :uid"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }
}
