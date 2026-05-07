<?php
/*
 * app/models/AuditLog.php
 * ------------------------
 * Reads from the audit_logs table (F41 — System Audit Trail).
 * Writes are done by the logAction() helper function in functions.php.
 * This model is only used for reading logs in the Admin panel.
 */

class AuditLog
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }


    public function getRecent(): array
    {
        $stmt = $this->db->prepare(
            "SELECT al.*, u.name AS user_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT 200"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

  
    public function getByEntity(string $entity): array
    {
        $stmt = $this->db->prepare(
            "SELECT al.*, u.name AS user_name
             FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
             WHERE al.entity = :entity
             ORDER BY al.created_at DESC LIMIT 100"
        );
        $stmt->execute([':entity' => $entity]);
        return $stmt->fetchAll();
    }
}
