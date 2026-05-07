<?php
/*
 * app/controllers/AdminController.php
 * --------------------------------------
 * Handles all system administration tasks.
 * Implements:
 *   F34 — Reading History Privacy Toggle (updatePrivacy)
 *   F37 — GDPR Compliance: export + delete (exportData, deleteAccount)
 *   F39 — Platform Sustainability Audit (sustainabilityReport)
 *   F41 — System Audit Trail viewer (auditLog)
 *         User management: list, search, role change
 *         Store approval workflow
 *         Dispute management
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Store.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Dispute.php';
require_once __DIR__ . '/../models/StoreApplication.php';
require_once __DIR__ . '/../models/Notification.php';

class AdminController extends Controller
{
    // ── index() ─────────────────────────────────────────────────────────────
    // Route to the correct admin section (default: user list).
    public function index(): void
    {
        requireRole(['SYSTEM_ADMIN']);
        $this->redirect(BASE_URL . 'index.php?page=admin&action=users');
    }

    // ── users() ─────────────────────────────────────────────────────────────
    // List all users; support a search query.
    public function users(): void
    {
        requireRole(['SYSTEM_ADMIN']);
        $userModel = new User();
        $query     = trim($_GET['q'] ?? '');
        $users     = $query ? $userModel->search($query) : $userModel->getAll();
        $this->view('admin/users', ['users' => $users, 'query' => $query]);
    }

    // ── changeRole() ─────────────────────────────────────────────────────────
    // Admin changes a user's role. Cannot change own role.
    public function changeRole(): void
    {
        requireRole(['SYSTEM_ADMIN']);
        $userId    = (int)($_POST['user_id'] ?? 0);
        $newRole   =       $_POST['role']    ?? '';
        $allowedRoles = ['READER','BOOKSTORE_OWNER','CLUB_ORGANIZER','AUTHOR','SYSTEM_ADMIN'];

        if ($userId === currentUserId()) {
            setFlash('danger', 'You cannot change your own role.');
            $this->redirect(BASE_URL . 'index.php?page=admin&action=users');
        }

        if (!in_array($newRole, $allowedRoles)) {
            setFlash('danger', 'Invalid role.');
            $this->redirect(BASE_URL . 'index.php?page=admin&action=users');
        }

        (new User())->updateRole($userId, $newRole);
        logAction('CHANGE_ROLE', 'users', $userId, "New role: {$newRole}");
        setFlash('success', "User role updated to {$newRole}.");
        $this->redirect(BASE_URL . 'index.php?page=admin&action=users');
    }
        // ── applyStore() ───────────────────────────────────────────────────────
    // F36: Let a BOOKSTORE_OWNER apply to open a new store.
    public function applyStore(): void
    {
        // السماح لصاحب المكتبة إنه يدخل الصفحة دي
        requireRole(['BOOKSTORE_OWNER', 'SYSTEM_ADMIN']); 

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // تجميع البيانات بنفس أسماء الـ Keys اللي الموديل طالبها
            $data = [
                ':user_id'     => currentUserId(),
                ':store_name'  => sanitize($_POST['store_name'] ?? ''),
                ':description' => sanitize($_POST['description'] ?? ''),
                ':city'        => sanitize($_POST['city'] ?? '')
            ];

            $appModel = new StoreApplication();
            
            if ($appModel->create($data)) {
                setFlash('success', 'Your store application has been submitted and is pending admin approval.');
                // توجيهه للوحة التحكم بعد النجاح
                $this->redirect(BASE_URL . 'index.php?page=dashboard'); 
            } else {
                setFlash('danger', 'Error submitting application.');
                $this->redirect(BASE_URL . 'index.php?page=admin&action=applyStore');
            }
        } else {
            // لو بيفتح الصفحة لأول مرة (GET) نعرضله الفورم
            $this->view('admin/apply_store'); 
        }
    }

    // ── stores() ─────────────────────────────────────────────────────────────
    // Admin sees all stores and approves / rejects pending ones.
   // ── stores() ─────────────────────────────────────────────────────────────
    // Admin sees all stores and approves / rejects pending ones.
    public function stores(): void
    {
        requireRole(['SYSTEM_ADMIN']);
        $storeModel = new Store();
        $appModel   = new StoreApplication(); // ضفنا الموديل بتاع الطلبات
        
        $stores  = $storeModel->getAll();
        $pending = $appModel->getPending(); // خلينا الطلبات تيجي من الجدول الصح
        
        $this->view('admin/stores', ['stores' => $stores, 'pending' => $pending]);
    }

    // ── approveStore() ───────────────────────────────────────────────────────
    // Approve or reject a store application.
public function approveStore(): void
{
    requireRole(['SYSTEM_ADMIN']);
    $applicationId = (int)($_POST['store_id'] ?? 0);
    $status        =      $_POST['status']   ?? 'approved';

    $appModel = new StoreApplication();
    $storeModel = new Store();

    // 1. تحديث حالة الطلب
    $appModel->updateStatus($applicationId, $status);

    // 2. إذا كانت الموافقة "approved"، نقوم بإنشاء المحل فعلياً في جدول stores
    if ($status === 'approved') {
        // جلب بيانات الطلب عشان ناخد منها الاسم والمدينة واليوزر
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM store_applications WHERE id = :id");
        $stmt->execute([':id' => $applicationId]);
        $app = $stmt->fetch();

        if ($app) {
            $storeModel->create([
                ':owner_id' => $app['user_id'],
                ':name'     => $app['store_name'],
                ':city'     => $app['city'],
                ':region'   => 'General', // قيمة افتراضية أو خديها من الطلب لو موجودة
                ':is_verified' => 1
            ]);
        }
    }

    logAction('APPROVE_STORE', 'store_applications', $applicationId, "Status: {$status}");
    setFlash('success', "Store application {$status} and created officially.");
    $this->redirect(BASE_URL . 'index.php?page=admin&action=stores');
}

    // ── reports() ────────────────────────────────────────────────────────────
    // Show combined platform reports: orders by type, payout summary.
    public function reports(): void
    {
        requireRole(['SYSTEM_ADMIN']);
        require_once __DIR__ . '/../models/PayoutLedger.php';

        $orderModel  = new Order();
        $ledgerModel = new PayoutLedger();

        $ordersByType   = $orderModel->countByType();
        $payoutSummary  = $ledgerModel->getSummary();

        $this->view('admin/reports', compact('ordersByType', 'payoutSummary'));
    }

    // ── auditLog() ───────────────────────────────────────────────────────────
    // F41: View the system-wide audit trail.
    public function auditLog(): void
    {
        requireRole(['SYSTEM_ADMIN']);
        $logs = (new AuditLog())->getRecent();
        $this->view('admin/audit_log', ['logs' => $logs]);
    }

    // ── disputes() / resolveDispute() ────────────────────────────────────────
    // View open disputes and resolve them.
    public function disputes(): void
    {
        requireRole(['SYSTEM_ADMIN']);
        $disputes = (new Dispute())->getOpen();
        $this->view('admin/disputes', ['disputes' => $disputes]);
    }

public function resolveDispute(): void
    {
        requireRole(['SYSTEM_ADMIN']);
        $id         = (int)($_POST['dispute_id'] ?? 0);
        $resolution = trim($_POST['resolution'] ?? '');

        // 1. نجيب بيانات المشكلة قبل ما نحلها عشان نعرف مين اليوزر
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT user_id, order_id FROM disputes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $disputeInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($disputeInfo) {
            // 2. نقفل المشكلة في الداتابيز
            $disputeModel = new Dispute();
            if ($disputeModel->resolve($id, $resolution)) {
                
                // 3. نبعت الإشعار
                $notifModel = new Notification();
                $userId  = $disputeInfo['user_id'];
                $orderId = $disputeInfo['order_id'];
                $msg     = "Admin has resolved your dispute regarding Order #{$orderId}. Resolution: " . substr($resolution, 0, 50) . "...";
                $link    = BASE_URL . "index.php?page=orders&action=show&id={$orderId}";
                
                $notifModel->create($userId, $msg, $link);
                
                logAction('RESOLVE_DISPUTE', 'disputes', $id);
                setFlash('success', 'Dispute resolved and user notified successfully!');
            } else {
                setFlash('danger', 'Could not update dispute status.');
            }
        } else {
            setFlash('danger', 'Dispute not found.');
        }

        $this->redirect(BASE_URL . 'index.php?page=admin&action=disputes');
    }

    // ── sustainabilityReport() ───────────────────────────────────────────────
    // F39: Calculate CO2 savings from local pickup vs. delivery.
    public function sustainabilityReport(): void
    {
        requireRole(['SYSTEM_ADMIN']);
        $CARBON_PER_PICKUP = 2.3; // kg of CO2 saved per local pickup order

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE type = 'pickup'");
        $stmt->execute();
        $pickupCount = (int)$stmt->fetchColumn();

        $stmt2 = $db->prepare("SELECT COUNT(*) FROM orders WHERE type = 'delivery'");
        $stmt2->execute();
        $deliveryCount = (int)$stmt2->fetchColumn();

        $carbonSaved = round($pickupCount * $CARBON_PER_PICKUP, 1);

        $this->view('admin/sustainability', [
            'pickupCount'   => $pickupCount,
            'deliveryCount' => $deliveryCount,
            'carbonSaved'   => $carbonSaved,
        ]);
    }

    // ── updatePrivacy() ──────────────────────────────────────────────────────
    // F34: Let a user toggle their reading history privacy setting.
    public function updatePrivacy(): void
    {
        requireLogin();
        $privacy = $_POST['privacy'] ?? 'PRIVATE';
        $userId  = currentUserId();

        if (!in_array($privacy, ['PUBLIC', 'PRIVATE'])) {
            setFlash('danger', 'Invalid privacy setting.');
            $this->redirect(BASE_URL . 'index.php?page=settings');
        }

        (new User())->updatePrivacy($userId, $privacy);
        $_SESSION['privacy'] = $privacy;

        logAction('UPDATE_PRIVACY', 'users', $userId, "Privacy: {$privacy}");
        setFlash('success', "Reading history privacy set to: {$privacy}");
        $this->redirect(BASE_URL . 'index.php?page=settings');
    }

    // ── settings() ───────────────────────────────────────────────────────────
    // User settings page for privacy controls.
    public function settings(): void
    {
        requireLogin();
        $user = (new User())->getById(currentUserId());
        $this->view('admin/settings', ['user' => $user]);
    }

    // ── exportData() ─────────────────────────────────────────────────────────
    // F37: Export all personal data to a downloadable JSON file (GDPR).
 // ── exportData() ─────────────────────────────────────────────────────────
    // F37: Export all personal data to a downloadable JSON file (GDPR).
   // ── exportData() ─────────────────────────────────────────────────────────
    // F37: Export all personal data to a downloadable JSON file (GDPR).
    public function exportData(): void
    {
        requireLogin();
        $userId = currentUserId();
        $db     = Database::getInstance()->getConnection();

        // Collect data from all user-related tables
        $data   = [];
        $tables = ['users', 'orders', 'read_books', 'loans', 'nominations', 'club_members'];

        foreach ($tables as $table) {
            try {
                $col  = ($table === 'users') ? 'id' : 'user_id';
                $stmt = $db->prepare("SELECT * FROM {$table} WHERE {$col} = :uid");
                $stmt->execute([':uid' => $userId]);
                $data[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // لو جدول مش موجود أو فيه مشكلة
                $data[$table] = "Error fetching data or table does not exist.";
            }
        }

        // 🚨 التعديل هنا: فحص وإنشاء المجلد أوتوماتيكياً لو مش موجود
        $uploadDir = __DIR__ . '/../../public/assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // true عشان لو مجلد assets كمان مش موجود يكريت الاتنين
        }

        // Build the filename and save to uploads folder
        $filename = "gdpr_export_{$userId}_" . time() . ".json";
        $filepath = $uploadDir . $filename;
        
        // حفظ الملف
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));

        // Log the export in a GDPR table if it exists (best effort)
        try {
            $stmt2 = $db->prepare(
                "INSERT INTO gdpr_exports (user_id, request_type, status, file_path)
                 VALUES (:uid, 'export', 'completed', :path)"
            );
            $stmt2->execute([':uid' => $userId, ':path' => $filename]);
        } catch (PDOException $e) { /* Table may not exist yet — skip */ }

        // Trigger file download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    }

    // ── deleteAccount() ──────────────────────────────────────────────────────
    // F37: Anonymize user data (GDPR right to erasure). Preserves referential integrity.
    public function deleteAccount(): void
    {
        requireLogin();
        $userId = currentUserId();

        (new User())->anonymize($userId);

        // Log before we destroy the session
        try {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "INSERT INTO gdpr_exports (user_id, request_type, status)
                 VALUES (:uid, 'delete', 'completed')"
            );
            $stmt->execute([':uid' => $userId]);
        } catch (PDOException $e) { /* skip */ }

        session_destroy();
        header('Location: ' . BASE_URL . 'index.php?page=home');
        exit();
    }
}
