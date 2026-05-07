<?php
/*
 * app/models/Event.php
 * ---------------------
 * Handles all database operations for the 'events' table.
 * Events are created by authors and can be virtual (with a stream URL)
 * or physical (with a city and venue).
 */

class Event
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * جلب كافة الفعاليات، مع ترتيبها حسب التاريخ (الأقرب أولاً).
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, u.name AS organizer_name
             FROM events e LEFT JOIN users u ON u.id = e.organizer_id
             ORDER BY e.event_date ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * جلب بيانات فعالية معينة بواسطة المعرف (ID).
     * 🛠️ تم تعديل حساب tickets_sold ليقتصر على الحجوزات المؤكدة فقط.
     */
    public function getById(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, u.name AS organizer_name,
                    (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'confirmed') AS tickets_sold
             FROM events e LEFT JOIN users u ON u.id = e.organizer_id
             WHERE e.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * جلب كافة الفعاليات التي أنشأها منظم معين.
     */
    public function getByOrganizer(int $organizerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM events WHERE organizer_id = :org ORDER BY event_date DESC"
        );
        $stmt->execute([':org' => $organizerId]);
        return $stmt->fetchAll();
    }

    /**
     * إنشاء فعالية جديدة في قاعدة البيانات.
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO events
             (organizer_id, title, description, event_date, capacity, stream_url, city, ticket_price)
             VALUES (:organizer_id, :title, :description, :event_date, :capacity, :stream_url, :city, :ticket_price)"
        );
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    /**
     * تحديث حالة الفعالية (مثل: upcoming, live, ended).
     */
    public function updateEventStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE events SET status = :status WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    /**
     * الحصول على إجمالي عدد الفعاليات المسجلة.
     */
    public function count(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM events");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}