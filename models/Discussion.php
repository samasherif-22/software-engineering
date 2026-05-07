<?php
/*
 * app/models/Discussion.php
 * --------------------------
 * Handles club discussion threads (F13 — Synchronized Discussion Cycles).
 * A discussion can require a minimum chapter to be read before access is granted.
 */

class Discussion
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all discussion threads for a club.
     *
     * @param int $clubId
     * @return array
     */
  public function getByClub(int $clubId): array
{
    $stmt = $this->db->prepare(
        "SELECT d.*, u.name AS author_name 
         FROM discussions d
         LEFT JOIN users u ON u.id = d.author_id 
         WHERE d.club_id = :club_id
         ORDER BY d.created_at DESC"
    );
    $stmt->execute([':club_id' => $clubId]);
    return $stmt->fetchAll();
}

    /**
     * Get a single discussion thread by ID.
     *
     * @param int $id
     * @return array|false
     */
    public function getById(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, u.name AS poster_name
             FROM discussions d JOIN users u ON u.id = d.user_id
             WHERE d.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Create a new discussion thread.
     *
     * @param array $data  Keys: club_id, user_id, title, body, required_chapter
     * @return bool
     */
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO discussions (club_id, user_id, title, body, required_chapter)
             VALUES (:club_id, :user_id, :title, :body, :required_chapter)"
        );
        return $stmt->execute($data);
    }
}
