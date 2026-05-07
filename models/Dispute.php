<?php
/*
 * app/models/Dispute.php
 * -----------------------
 * Handles order/transaction disputes submitted by readers or store owners.
 * Admin can review and resolve disputes.
 */

class Dispute
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getOpen(): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, u.name AS reporter_name
             FROM disputes d LEFT JOIN users u ON u.id = d.user_id
             WHERE d.status = 'open' ORDER BY d.created_at ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO disputes (user_id, order_id, reason)
             VALUES (:user_id, :order_id, :reason)"
        );
        return $stmt->execute($data);
    }

    // دي الدالة اللي كانت ناقصة واللي بتعمل Update في الداتابيز
    public function resolve(int $id, string $resolution): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE disputes SET status = 'resolved', resolution = :res WHERE id = :id"
        );
        return $stmt->execute([':res' => $resolution, ':id' => $id]);
    }
}