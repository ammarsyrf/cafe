<?php
// File: middleware.php 
// Middleware untuk kontrol akses dan keamanan

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Middleware
{
    public static function applySecurityMiddleware()
    {
        // Basic security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
    }

    public static function requireAdminAuth()
    {
        if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['role'] !== 'superadmin')) {
            session_unset();
            session_destroy();
            header('Location: ../auth/admin_login.php?error=auth_required');
            exit();
        }
    }

    public static function requireKasirAuth()
    {
        if (!isset($_SESSION['kasir']) || empty($_SESSION['kasir']['id'])) {
            session_unset();
            session_destroy();
            header('Location: login_kasir.php?error=auth_required');
            exit();
        }
    }

    public static function requireMemberAuth()
    {
        if (!isset($_SESSION['member']) || empty($_SESSION['member']['id'])) {
            session_unset();
            session_destroy();
            header('Location: ../auth/login.php?error=auth_required');
            exit();
        }
    }
}
