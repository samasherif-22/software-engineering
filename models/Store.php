<?php
/*
 * app/models/Store.php
 * ---------------------
 * Handles all database operations for the 'stores' table.
 * A store is owned by a user with role BOOKSTORE_OWNER.
 */

class Store
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all stores, newest first.
     *
     * @return array
     */
 // جوه ملف app/models/Store.php
public function getAll(): array
{
    $stmt = $this->db->prepare(
        "SELECT s.*, u.name AS owner_name, 
         CASE WHEN s.is_verified = 1 THEN 'approved' ELSE 'pending' END AS status 
         FROM stores s
         LEFT JOIN users u ON u.id = s.owner_id
         ORDER BY s.id DESC"
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

    /**
     * Get a single store by its ID.
     *
     * @param int $id
     * @return array|false
     */
    public function getById(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.name AS owner_name
             FROM stores s LEFT JOIN users u ON u.id = s.owner_id
             WHERE s.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Get the store owned by a specific user.
     *
     * @param int $ownerId
     * @return array|false
     */
    public function getByOwner(int $ownerId)
    {
        $stmt = $this->db->prepare("SELECT * FROM stores WHERE owner_id = :owner_id LIMIT 1");
        $stmt->execute([':owner_id' => $ownerId]);
        return $stmt->fetch();
    }

    /**
     * Get all stores with a pending approval status.
     *
     * @return array
     */
    public function getPending(): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.name AS owner_name, u.email AS owner_email
             FROM stores s LEFT JOIN users u ON u.id = s.owner_id
             WHERE s.status = 'pending' ORDER BY s.id ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Insert a new store application.
     *
     * @param array $data  Keys: owner_id, name, description, city, region, address, phone
     * @return bool
     */
    public function create(array $data): bool
{
    $stmt = $this->db->prepare(
        "INSERT INTO stores (owner_id, name, city, region, is_verified) 
         VALUES (:owner_id, :name, :city, :region, :is_verified)"
    );
    // لو اسم العمود في الداتابيز user_id بدل owner_id، غيري الكلمة في جملة الـ INSERT اللي فوق
    return $stmt->execute($data);
}

    /**
     * Approve or reject a store application (admin action).
     *
     * @param int    $id      Store ID
     * @param string $status  'approved' or 'rejected'
     * @return bool
     */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE stores SET status = :status WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    /**
     * Count total stores in the system.
     *
     * @return int
     */
    public function count(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM stores");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
