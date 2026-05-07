<?php
/*
 * app/models/Book.php
 * --------------------
 * Handles all database operations for the 'books' table.
 */

class Book {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // --- 1. البحث والقراءة (Read) ---

 public function getAll($preferredStoreId = 0)
    {
        $db = Database::getInstance()->getConnection();
        
        // استعلام أساسي بيجيب الكتب مع اسم المكتبة
        $sql = "SELECT b.*, s.name as store_name 
                FROM books b 
                LEFT JOIN stores s ON b.store_id = s.id";

        if ($preferredStoreId > 0) {
            // 📌 لو فيه رقم مكتبة مبعوت (يعني ده صاحب مكتبة)، نعمل Pin لكتبه في الأول
            // في الـ SQL: (b.store_id = :pref_id) بترجع 1 لو ترو، و 0 لو فولس
            // ولما نرتب DESC، الـ 1 (كتبه) هتيجي فوق، وبعدين باقي الكتب تترتب بالأحدث
            $sql .= " ORDER BY (b.store_id = :pref_id) DESC, b.created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':pref_id', $preferredStoreId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // لو يوزر عادي أو أدمن، نعرض الكتب مترتبة بالأحدث وخلاص
            $sql .= " ORDER BY b.created_at DESC";
            $stmt = $db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function getById(int $id) {
        $stmt = $this->db->prepare(
            "SELECT b.*, s.name AS store_name, s.owner_id
             FROM books b
             LEFT JOIN stores s ON s.id = b.store_id
             WHERE b.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function getByStore(int $storeId): array {
        $stmt = $this->db->prepare("SELECT * FROM books WHERE store_id = :store_id ORDER BY id DESC");
        $stmt->execute([':store_id' => $storeId]);
        return $stmt->fetchAll();
    }

    public function getStaffPicks(): array {
        $stmt = $this->db->prepare("SELECT * FROM books WHERE is_staff_pick = 1 ORDER BY RAND() LIMIT 6");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function search(string $query): array {
        $stmt = $this->db->prepare(
            "SELECT id, title, author_name, final_price, condition_grade, cover_url
             FROM books
             WHERE title LIKE :q OR author_name LIKE :q
             LIMIT 20"
        );
        $stmt->execute([':q' => '%' . $query . '%']);
        return $stmt->fetchAll();
    }

    public function getRecommended(string $genre, int $excludeId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM books
             WHERE genre = :genre AND id != :id AND stock_qty > 0
             ORDER BY RAND() LIMIT 3"
        );
        $stmt->execute([':genre' => $genre, ':id' => $excludeId]);
        return $stmt->fetchAll();
    }

    // --- 2. الإضافة والتعديل (Write) ---

    public function create(array $data) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO books (store_id, isbn, title, author_name, genre, base_price, final_price, condition_grade, stock_qty, cover_url, description, is_rare, is_staff_pick, is_signed)
                 VALUES (:store_id, :isbn, :title, :author_name, :genre, :base_price, :final_price, :condition_grade, :stock_qty, :cover_url, :description, 0, 0, 0)"
            );
            if ($stmt->execute($data)) return $this->db->lastInsertId();
            return false;
        } catch (PDOException $e) { die("❌ Error (Create): " . $e->getMessage()); }
    }

    public function update(array $data): bool {
        try {
            $stmt = $this->db->prepare(
                "UPDATE books SET title=:title, author_name=:author_name, genre=:genre, isbn=:isbn, base_price=:base_price, final_price=:final_price, condition_grade=:condition_grade, stock_qty=:stock_qty, description=:description, cover_url=:cover_url WHERE id = :id"
            );
            return $stmt->execute($data);
        } catch (PDOException $e) { die("❌ Error (Update): " . $e->getMessage()); }
    }

    public function updateCover(int $id, string $url): bool {
        $stmt = $this->db->prepare("UPDATE books SET cover_url = :url WHERE id = :id");
        return $stmt->execute([':url' => $url, ':id' => $id]);
    }

    // --- 3. العمليات الخاصة (Logic) ---

    public function toggleStaffPick(int $id): bool {
        $stmt = $this->db->prepare("UPDATE books SET is_staff_pick = NOT is_staff_pick WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function decrementStock(int $id): bool {
        $stmt = $this->db->prepare("UPDATE books SET stock_qty = stock_qty - 1 WHERE id = :id AND stock_qty > 0");
        return $stmt->execute([':id' => $id]);
    }

    public function incrementStock(int $id): bool {
        $stmt = $this->db->prepare("UPDATE books SET stock_qty = stock_qty + 1 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM books WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function count(): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM books");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
    /**

     * Get only the books purchased by a specific user.
     */
    public function getPurchasedByUser(int $userId): array
    {
        // تم التعديل لاستخدام item_id و item_type بناءً على هيكل قاعدة البيانات
        $stmt = $this->db->prepare("
            SELECT DISTINCT b.id, b.title 
            FROM books b
            JOIN order_items oi ON b.id = oi.item_id AND oi.item_type = 'book'
            JOIN orders o ON o.id = oi.order_id
            WHERE o.user_id = :uid 
        ");
        
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 