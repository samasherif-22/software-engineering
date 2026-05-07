<?php
/*
 * app/models/Loan.php
 * --------------------
 * Handles peer-to-peer book lending within a club (F18).
 * Tracks lender, borrower, due date, and return status.
 */

class Loan
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all loans where the user is the lender.
     *
     * @param int $userId
     * @return array
     */
    public function getLent(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, b.title AS book_title, u.name AS borrower_name,
                    CASE WHEN l.returned = 0 AND l.due_date < NOW() THEN 1 ELSE 0 END AS is_overdue
             FROM loans l
             JOIN books b ON b.id = l.book_id
             JOIN users u ON u.id = l.borrower_id
             WHERE l.lender_id = :uid ORDER BY l.id DESC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get all loans where the user is the borrower.
     *
     * @param int $userId
     * @return array
     */
    public function getBorrowed(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, b.title AS book_title, u.name AS lender_name,
                    CASE WHEN l.returned = 0 AND l.due_date < NOW() THEN 1 ELSE 0 END AS is_overdue
             FROM loans l
             JOIN books b ON b.id = l.book_id
             JOIN users u ON u.id = l.lender_id
             WHERE l.borrower_id = :uid ORDER BY l.id DESC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Create a new lending record.
     *
     * @param array $data  Keys: lender_id, borrower_id, book_id, due_date
     * @return int  New loan ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO loans (lender_id, borrower_id, book_id, due_date)
             VALUES (:lender_id, :borrower_id, :book_id, :due_date)"
        );
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Mark a loan as returned.
     *
     * @param int $id  Loan ID
     * @return bool
     */
    public function markReturned(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE loans SET returned = 1, returned_at = NOW() WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }
}
