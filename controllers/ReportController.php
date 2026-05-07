<?php
/*
 * app/controllers/ReportController.php
 * ---------------------------------------
 * Generates printable HTML reports for store owners and system admin.
 * Reports are formatted as full HTML pages with print-friendly styles.
 * No external PDF library needed — browser's File → Print → Save as PDF works.
 */

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Book.php';
require_once __DIR__ . '/../models/Store.php';
require_once __DIR__ . '/../models/PayoutLedger.php';
require_once __DIR__ . '/../models/AuditLog.php';

class ReportController extends Controller
{
    // ── index() ─────────────────────────────────────────────────────────────
    // Show the reports menu.
    public function index(): void
    {
        requireRole(['BOOKSTORE_OWNER', 'SYSTEM_ADMIN']);
        $this->view('admin/reports', []);
    }

   
    // Printable sales report for a store: all orders with totals.
   // ── salesReport() ────────────────────────────────────────────────────────
    public function salesReport(): void
    {
        requireRole(['BOOKSTORE_OWNER', 'SYSTEM_ADMIN']);
        $userId     = currentUserId();
        $storeModel = new Store();
        $store      = $storeModel->getByOwner($userId);

        if (!$store && currentRole() !== 'SYSTEM_ADMIN') {
            setFlash('danger', 'You do not have an approved store.');
            $this->redirect(BASE_URL . 'index.php?page=dashboard');
            return;
        }

        $orderModel  = new Order();
        $ledgerModel = new PayoutLedger();

        $storeId = $store ? (int)$store['id'] : 0;

        
        $orders  = $storeId ? $orderModel->getByStore($storeId) : [];
        $payout  = $storeId ? $ledgerModel->getSummary($storeId) : []; 

        $this->view('admin/reports', [
            'store'      => $store,
            'orders'     => $orders,
            'payout'     => $payout,
            'reportType' => 'sales',
        ]);
    }
    // ── inventoryReport() ─────────────────────────────────────────────────────
    // Printable inventory list for a store owner.
    public function inventoryReport(): void
    {
        requireRole(['BOOKSTORE_OWNER', 'SYSTEM_ADMIN']);
        $userId     = currentUserId();
        $storeModel = new Store();
        $store      = $storeModel->getByOwner($userId);

        $bookModel = new Book();
        $books     = $store ? $bookModel->getByStore($store['id']) : [];

        $this->view('admin/reports', [
            'store'      => $store,
            'books'      => $books,
            'reportType' => 'inventory',
        ]);
    }
}
