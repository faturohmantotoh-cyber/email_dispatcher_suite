<?php
// public/logout.php - Secure logout
session_start();
require_once __DIR__ . '/../config_db.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/security.php';

// Log logout event
if (!empty($_SESSION['user'])) {
    try {
        $pdo = DB::conn();
        SecurityManager::logSecurityEvent('user_logout', $_SESSION['user']['id'], 'User logged out');
    } catch (Exception $e) {
        // Silent fail
    }
}

// Destroy session securely
$_SESSION = [];
session_regenerate_id(true);
session_destroy();

// Clear cookies
setcookie('remember_token', '', time() - 3600, '/');
setcookie('PHPSESSID', '', time() - 3600, '/');

header('Location: login.php?logout=1');
exit;