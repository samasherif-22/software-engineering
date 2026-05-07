<?php
/*
 * app/models/Hold.php
 * --------------------
 * Handles digital holds-on-shelf (F8).
 * A hold reserves a book for 24 hours without payment.
 * The book's stock_qty is decremented while the hold is active.
 */

class Hold
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Check if a user has an unexpired active hold on a specific book.
     *
     * @param int $userId
     * @param int $bookId
     * @return array|false  The hold row, or false if none
     */
    public function getActiveHold(int $userId, int $bookId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM holds
             WHERE user_id = :uid AND book_id = :bid AND expires_at > NOW()"
        );
        $stmt->execute([':uid' => $userId, ':bid' => $bookId]);
        return $stmt->fetch();
    }

    /**
     * Get all active holds for a specific user, with book details.
     *
     * @param int $userId
     * @return array
     */
    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.*, b.title, b.author_name, b.cover_url
             FROM holds h JOIN books b ON b.id = h.book_id
             WHERE h.user_id = :uid AND h.expires_at > NOW()
             ORDER BY h.expires_at ASC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Create a new 24-hour hold on a book for a user.
     *
     * @param int $userId
     * @param int $bookId
     * @return bool
     */
    public function create(int $userId, int $bookId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO holds (user_id, book_id, expires_at)
             VALUES (:uid, :bid, DATE_ADD(NOW(), INTERVAL 24 HOUR))"
        );
        return $stmt->execute([':uid' => $userId, ':bid' => $bookId]);
    }

    /**
     * Delete a hold (e.g., when user converts hold to order).
     *
     * @param int $id  Hold ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM holds WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
