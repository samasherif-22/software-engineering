<?php
/*
 * app/helpers/auth.php
 * ---------------------
 * Authentication and Role-Based Access Control (RBAC) helpers.
 */

/**
 * Redirect to the login page if the user is not logged in.
 */
function requireLogin(): void
{
    // التأكد من وجود جلسة عمل
    if (!isset($_SESSION['user_id'])) {
        
        $currentPage = $_GET['page'] ?? '';
        
        // السماح بالبقاء في صفحة اللوجين أو التسجيل بدون تحويل
        if ($currentPage !== 'login' && $currentPage !== 'register') {
            header('Location: index.php?page=login');
            exit();
        }
    }
}

/**
 * Restrict access based on roles.
 */
function requireRole(array $allowedRoles): void
{
    requireLogin();

    if (!in_array($_SESSION['role'] ?? '', $allowedRoles)) {
        http_response_code(403);
        echo "<!DOCTYPE html><html><head><title>Access Denied</title>
              <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
              </head><body class='bg-light d-flex align-items-center justify-content-center' style='min-height:100vh'>
              <div class='text-center'>
                <h1 class='display-1 fw-bold text-warning'>403</h1>
                <p class='lead'>Access Denied</p>
                <p class='text-muted'>You do not have permission to view this page.</p>
                <a href='index.php?page=home' class='btn btn-primary'>Back to Home</a>
              </div></body></html>";
        exit();
    }
}

/**
 * دالات مساعدة للتحقق من حالة المستخدم
 */
function isLoggedIn(): bool { return isset($_SESSION['user_id']); }

function currentRole(): string { return $_SESSION['role'] ?? ''; }

function currentUserId(): ?int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }

function currentUserName(): string { return $_SESSION['name'] ?? 'Guest'; }