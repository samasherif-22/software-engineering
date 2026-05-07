<?php
/*
 * app/models/Order.php
 * ---------------------
 * Handles all database operations for the 'orders' table.
 * An order is placed by a reader and fulfilled by a bookstore.
 * Supports status transitions: placed → ready → collected / cancelled.
 */

class Order
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all orders for a specific user (reader's order history).
     *
     * @param int $userId
     * @return array
     */
    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, s.name AS store_name
             FROM orders o LEFT JOIN stores s ON s.id = o.store_id
             WHERE o.user_id = :uid ORDER BY o.id DESC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get all orders for a specific store (owner's order management).
     *
     * @param int $storeId
     * @return array
     */
    public function getByStore(int $storeId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, u.name AS customer_name
             FROM orders o LEFT JOIN users u ON u.id = o.user_id
             WHERE o.store_id = :sid ORDER BY o.id DESC"
        );
        $stmt->execute([':sid' => $storeId]);
        return $stmt->fetchAll();
    }

    /**
     * Get a single order by its ID, including customer and store info.
     *
     * @param int $id
     * @return array|false
     */
    public function getById(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, u.name AS customer_name, s.name AS store_name
             FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             LEFT JOIN stores s ON s.id = o.store_id
             WHERE o.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Create a new order (placed status).
     *
     * @param array $data  Keys: user_id, store_id, subtotal, type ('pickup'/'delivery')
     * @return int  The new order ID (lastInsertId)
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO orders (user_id, store_id, subtotal, type, status)
             VALUES (:user_id, :store_id, :subtotal, :type, 'placed')"
        );
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update the status of an order (F2 — Click-and-Collect workflow).
     *
     * @param int    $id        Order ID
     * @param string $newStatus New status string
     * @return bool
     */
    public function updateStatus(int $id, string $newStatus): bool
    {
        $stmt = $this->db->prepare("UPDATE orders SET status = :status WHERE id = :id");
        return $stmt->execute([':status' => $newStatus, ':id' => $id]);
    }

    /**
     * Update tax amount and total on an order (F11).
     *
     * @param int   $id         Order ID
     * @param float $taxAmount
     * @param float $total
     * @return bool
     */
    public function applyTax(int $id, float $taxAmount, float $total): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE orders SET tax_amount = :tax, total = :total WHERE id = :id"
        );
        return $stmt->execute([':tax' => $taxAmount, ':total' => $total, ':id' => $id]);
    }

    /**
     * Count total orders in the system (admin dashboard).
     *
     * @return int
     */
    public function count(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Count all orders grouped by type ('pickup' vs 'delivery').
     *
     * @return array  [['type' => '...', 'cnt' => N], ...]
     */
    public function countByType(): array
    {
        $stmt = $this->db->prepare(
            "SELECT type, COUNT(*) AS cnt FROM orders GROUP BY type"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
