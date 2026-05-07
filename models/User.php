<?php
/*
 * app/models/User.php
 * --------------------
 * Handles all database operations for the 'users' table.
 * Each method performs one specific query — nothing more.
 * Passwords are NEVER stored here — always hashed before calling create().
 */

class User
{
    private PDO $db;

    public function __construct()
    {
        // Get the shared singleton database connection
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all users, newest first.
     *
     * @return array
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT id, name, email, role, privacy, loyalty_points, created_at FROM users ORDER BY id DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find a single user by their primary key.
     *
     * @param int $id
     * @return array|false
     */
    public function getById(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Find a user by their email address. Used during login.
     *
     * @param string $email
     * @return array|false
     */
  // app/models/User.php

public function getByEmail(string $email)
{
    $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    return $stmt->fetch(PDO::FETCH_ASSOC); // تأكدي من وجود FETCH_ASSOC
}

    /**
     * Insert a new user record into the database.
     *
     * @param array $data  Keys: name, email, password_hash, role
     * @return bool
     */
// app/models/User.php
        public function create(array $data): bool
        {
            $stmt = $this->db->prepare(
                "INSERT INTO users (name, email, password_hash, role)
                VALUES (:name, :email, :password_hash, :role)"
            );
            return $stmt->execute($data);
        }
    /**
     * Update a user's role (used by admin).
     *
     * @param int    $id    User ID
     * @param string $role  New role value
     * @return bool
     */
    public function updateRole(int $id, string $role): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET role = :role WHERE id = :id");
        return $stmt->execute([':role' => $role, ':id' => $id]);
    }

    /**
     * Update privacy setting for a user ('PUBLIC' or 'PRIVATE').
     *
     * @param int    $id
     * @param string $privacy
     * @return bool
     */
    public function updatePrivacy(int $id, string $privacy): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET privacy = :privacy WHERE id = :id");
        return $stmt->execute([':privacy' => $privacy, ':id' => $id]);
    }

    /**
     * Anonymize a user's personal data (GDPR delete request).
     * We do NOT delete the row to preserve referential integrity.
     *
     * @param int $id
     * @return bool
     */
    public function anonymize(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users
             SET name = 'Deleted User',
                 email = CONCAT('deleted_', id, '@anon.com'),
                 password_hash = '',
                 privacy = 'PRIVATE'
             WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Search users by name or email (admin panel).
     *
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, email, role, created_at FROM users
             WHERE name LIKE :q OR email LIKE :q
             ORDER BY name ASC LIMIT 50"
        );
        $stmt->execute([':q' => '%' . $query . '%']);
        return $stmt->fetchAll();
    }

    /**
     * Count total registered users.
     *
     * @return int
     */
    public function count(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
