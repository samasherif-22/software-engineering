<?php
/*
 * app/helpers/functions.php
 * --------------------------
 * General-purpose helper functions used throughout the application.
 *
 *  logAction()   → writes a record to audit_logs after every important write.
 *  setFlash()    → stores a one-time alert message in the session.
 *  getFlash()    → retrieves and clears the flash message.
 *  sanitize()    → shortcut for htmlspecialchars() to prevent XSS.
 *  formatPrice() → formats a number as EGP currency.
 *
 * OBSERVER PATTERN CLASSES (for F32 — Author Event Notifications):
 *  Observer interface, NotificationObserver, EventSubject
 */

// ─────────────────────────────────────────────────────────────────────────────
// FLASH MESSAGES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Save a one-time alert message to show after a redirect.
 * The message is displayed once and then removed.
 *
 * @param string $type     Bootstrap alert type: 'success', 'danger', 'warning', 'info'
 * @param string $message  The message text to display
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message from the session.
 * Returns null if there is no pending flash message.
 *
 * @return array|null  ['type' => '...', 'message' => '...']
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// AUDIT LOGGING (F41)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Log an important action to the audit_logs table.
 * Call this after every create, update, or delete operation.
 *
 * @param string      $action    What happened ('CREATE_BOOK', 'DELETE_USER', etc.)
 * @param string      $entity    Which table was affected ('books', 'users', etc.)
 * @param int|null    $entityId  The primary key of the affected record
 * @param string|null $details   Extra detail string (optional)
 */
function logAction(string $action, string $entity, ?int $entityId = null, ?string $details = null): void
{
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "INSERT INTO audit_logs (user_id, action, entity, entity_id, details)
             VALUES (:user_id, :action, :entity, :entity_id, :details)"
        );
        $stmt->execute([
            ':user_id'   => $_SESSION['user_id'] ?? null,
            ':action'    => $action,
            ':entity'    => $entity,
            ':entity_id' => $entityId,
            ':details'   => $details,
        ]);
    } catch (Exception $e) {
        // If logging fails, don't crash the whole app — just silently skip
        error_log("logAction failed: " . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// UTILITY HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Escape HTML special characters to prevent XSS attacks.
 * Use this whenever you echo user-supplied data into HTML.
 *
 * @param mixed $value  The value to sanitize
 * @return string
 */
function sanitize($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a number as EGP currency.
 * Example: formatPrice(49.9) → "EGP 49.90"
 *
 * @param float $amount
 * @return string
 */
function formatPrice(float $amount): string
{
    return 'EGP ' . number_format($amount, 2);
}

/**
 * Returns the CSS badge class for a book condition grade.
 *
 * @param string $grade  'new', 'fine', 'good', or 'fair'
 * @return string  Bootstrap badge class name
 */
function conditionBadgeClass(string $grade): string
{
    return match (strtolower($grade)) {
        'new'   => 'badge-new',
        'fine'  => 'badge-fine',
        'good'  => 'badge-good',
        'fair'  => 'badge-fair',
        default => 'bg-secondary',
    };
}

// ─────────────────────────────────────────────────────────────────────────────
// OBSERVER PATTERN (F32 — Automated Author Notifications)
// ─────────────────────────────────────────────────────────────────────────────

/*
 * OBSERVER PATTERN EXPLAINED:
 *   - Subject  = the thing that triggers events (EventSubject)
 *   - Observer = something that reacts to those events (NotificationObserver)
 *   - When an author creates an event, EventSubject.notify() is called,
 *     which loops through all attached Observers and calls their update() method.
 *   - Each Observer does its own job independently (e.g., inserting a notification row).
 *   - Benefit: we can add more observers (e.g., send email) without changing the subject.
 */

/**
 * Observer interface — every observer must implement update().
 *
 * @param string $eventType  What happened (e.g. 'author_event_created')
 * @param array  $data       Context data for the event
 */
interface Observer
{
    public function update(string $eventType, array $data): void;
}

/**
 * Concrete Observer: inserts a notification row in the database
 * for every follower of the author who created the event.
 */
class NotificationObserver implements Observer
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * React to an event fired by the EventSubject.
     * Currently handles 'author_event_created'.
     *
     * @param string $eventType  The type of event
     * @param array  $data       Must contain: author_id, event_id, event_title
     */
    public function update(string $eventType, array $data): void
    {
        if ($eventType === 'author_event_created') {
            // Get all users who follow this author
            $stmt = $this->db->prepare(
                "SELECT user_id FROM follows WHERE author_id = :author_id"
            );
            $stmt->execute([':author_id' => $data['author_id']]);
            $followers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Insert a notification for each follower
            foreach ($followers as $follower) {
                $stmt2 = $this->db->prepare(
                    "INSERT INTO notifications (user_id, message, link)
                     VALUES (:user_id, :message, :link)"
                );
                $stmt2->execute([
                    ':user_id' => $follower['follower_id'],
                    ':message' => "An author you follow created a new event: " . $data['event_title'],
                    ':link'    => BASE_URL . "index.php?page=events&action=show&id=" . $data['event_id'],
                ]);
            }
        }
    }
}

/**
 * Subject class — holds a list of observers and notifies them all.
 * Used in EventController::store() to trigger author notifications.
 */
class EventSubject
{
    /** @var Observer[] */
    private array $observers = [];

    /**
     * Register an observer that will be notified on future events.
     *
     * @param Observer $observer
     */
    public function attach(Observer $observer): void
    {
        $this->observers[] = $observer;
    }

    /**
     * Notify all registered observers about an event.
     *
     * @param string $eventType  The event type string
     * @param array  $data       Context data to pass to each observer
     */
    public function notify(string $eventType, array $data): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($eventType, $data);
        }
    }
}
