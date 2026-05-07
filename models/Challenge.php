<?php
/*
 * app/models/Challenge.php
 * -------------------------
 * Handles yearly reading challenges (F19 — Reading Challenge Manager).
 * A user sets a goal (e.g., 52 books) for a given year.
 * Progress is calculated from the read_books table.
 */

class Challenge
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get a user's reading challenge for the current year.
     *
     * @param int $userId
     * @return array|false
     */
    public function getForCurrentYear(int $userId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM challenges WHERE user_id = :uid AND year = :year"
        );
        $stmt->execute([':uid' => $userId, ':year' => date('Y')]);
        return $stmt->fetch();
    }

    /**
     * Create a new yearly reading challenge for a user.
     *
     * @param int $userId
     * @param int $goalCount  Number of books to read
     * @return bool
     */
    public function create(int $userId, int $goalCount): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO challenges (user_id, goal_count, year)
             VALUES (:uid, :goal, :year)"
        );
        return $stmt->execute([':uid' => $userId, ':goal' => $goalCount, ':year' => date('Y')]);
    }

    /**
     * Count how many books the user has read so far this year.
     *
     * @param int $userId
     * @return int
     */
    public function countBooksReadThisYear(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM read_books
             WHERE user_id = :uid AND YEAR(finished_at) = :year"
        );
        $stmt->execute([':uid' => $userId, ':year' => date('Y')]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Mark a book as read for the current user.
     *
     * @param int $userId
     * @param int $bookId
     * @return bool
     */
    public function markRead(int $userId, int $bookId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO read_books (user_id, book_id, finished_at)
             VALUES (:uid, :bid, NOW())"
        );
        return $stmt->execute([':uid' => $userId, ':bid' => $bookId]);
    }
}
