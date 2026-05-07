<?php
/*
 * app/controllers/DisputeController.php
 * -------------------------------------
 * Handles dispute creation for readers.
 */

require_once BASE_PATH . '/app/models/Dispute.php';
require_once BASE_PATH . '/app/models/Notification.php'; // استدعاء موديل الإشعارات

class DisputeController extends Controller
{
    private Dispute $disputeModel;

    public function __construct()
    {
        $this->disputeModel = new Dispute();
    }

    public function create()
    {
        requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            $data = [
                'order_id' => (int)$_POST['order_id'],
                'user_id'  => currentUserId(), 
                'reason'   => trim($_POST['description']) 
            ];

            if ($this->disputeModel->create($data)) {
                
                // 1. نجيب كل حسابات الأدمن اللي في السيستم عشان نبعتلهم إشعار
                $db = Database::getInstance()->getConnection();
                $stmt = $db->query("SELECT id FROM users WHERE role = 'SYSTEM_ADMIN'");
                $admins = $stmt->fetchAll();
                
                // 2. نبعت الإشعار لكل أدمن فيهم
                $notifModel = new Notification();
                foreach ($admins as $admin) {
                    $msg = "New dispute opened for Order #" . $data['order_id'];
                    $link = BASE_URL . "index.php?page=admin&action=disputes";
                    $notifModel->create($admin['id'], $msg, $link);
                }

                // 3. نرجعه لصفحة الأوردر
                header('Location: ' . BASE_URL . 'index.php?page=orders&action=show&id=' . $data['order_id'] . '&success=DisputeSubmitted');
                exit;
            } else {
                echo "Error submitting dispute!";
            }
        }
    }
}