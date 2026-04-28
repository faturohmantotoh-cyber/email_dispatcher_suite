<?php
// public/auth_login.php - Login dengan keamanan berlapis
session_start();
require_once __DIR__ . '/../config_db.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/security.php';

$pdo = DB::conn();
SecurityManager::init($pdo);

// Cek CSRF
if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
  http_response_code(400);
  $_SESSION['flash'] = 'CSRF token tidak valid atau kadaluarsa. Coba login lagi.';
  header('Location: login.php');
  exit;
}

// Ambil input
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validasi dasar
if ($username === '' || $password === '') {
  $_SESSION['flash'] = 'Username atau password tidak boleh kosong.';
  header('Location: login.php');
  exit;
}

// Rate limiting check (5 attempts per 5 minutes per IP)
$clientIP = SecurityManager::getClientIP();
$rateLimitKey = 'login_' . $clientIP;
if (!SecurityManager::checkRateLimit($rateLimitKey, 5, 300)) {
  SecurityManager::logSecurityEvent('rate_limit_exceeded', $rateLimitKey, 'Login attempts exceeded');
  http_response_code(429);
  $_SESSION['flash'] = 'Terlalu banyak percobaan login. Coba lagi dalam beberapa menit.';
  header('Location: login.php');
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT id, username, email, display_name, password_hash, role, avatar, locked_until, requires_password_change 
    FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1
  ");
  $stmt->execute([$username, $username]);
  $user = $stmt->fetch();
  
  // Check if account is locked
  if ($user && $user['locked_until'] && time() < strtotime($user['locked_until'])) {
    SecurityManager::logSecurityEvent('login_locked_account', $user['id'], 'Account locked, login attempt blocked');
    http_response_code(403);
    $_SESSION['flash'] = 'Akun Anda terkunci karena terlalu banyak percobaan login gagal. Coba dalam 15 menit.';
    header('Location: login.php');
    exit;
  }

  // Verify password
  if ($user && password_verify($password, $user['password_hash'])) {
    // Reset failed login attempts
    $updateStmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, last_login = NOW(), locked_until = NULL WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
    // Log successful login
    SecurityManager::logSecurityEvent('successful_login', $user['id'], 'IP: ' . $clientIP);
    
    // Set session
    $sessionEmail = strtolower(trim((string)($user['email'] ?? '')));
    if (!filter_var($sessionEmail, FILTER_VALIDATE_EMAIL)) {
      $sessionEmail = '';
    }
    $usernameAsEmail = strtolower(trim((string)($user['username'] ?? '')));
    if (!filter_var($usernameAsEmail, FILTER_VALIDATE_EMAIL)) {
      $usernameAsEmail = '';
    }

    $_SESSION['user'] = [
      'id' => $user['id'],
      'name' => $user['display_name'] ?? $user['username'],
      'email' => $user['email'],
      'username' => $user['username'],
      'sender_email' => $sessionEmail !== '' ? $sessionEmail : $usernameAsEmail,
      'role' => $user['role'] ?? 'user',
      'avatar' => $user['avatar'] ?? null,
      'login_at' => time(),
      'login_time' => time()
    ];

    // Force password change if required
    if ($user['requires_password_change']) {
      header('Location: settings.php?force_password_change=1');
      exit;
    }

    // Remember login - dengan keamanan lebih tinggi
    if ($remember) {
      $rememberToken = bin2hex(random_bytes(32));
      setcookie('remember_token', $rememberToken, [
        'expires' => time() + 7*24*3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
      ]);
    }

    header('Location: index.php');
    exit;
  } else {
    // Failed login attempt
    if ($user) {
      // Increment failed attempts
      $failedCount = ($user['failed_login_attempts'] ?? 0) + 1;
      $updateStmt = $pdo->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?");
      $updateStmt->execute([$failedCount, $user['id']]);
      
      // Lock account if 5 failed attempts
      if ($failedCount >= 5) {
        SecurityManager::lockAccount($user['id']);
        SecurityManager::logSecurityEvent('account_locked', $user['id'], 'After 5 failed login attempts');
        $_SESSION['flash'] = 'Akun Anda terkunci setelah 5 percobaan gagal. Coba lagi dalam 15 menit.';
      }
      
      SecurityManager::logSecurityEvent('failed_login', $user['id'], 'Invalid password, IP: ' . $clientIP);
    } else {
      // Log failed login for non-existent user
      SecurityManager::logSecurityEvent('failed_login_unknown_user', $username, 'IP: ' . $clientIP);
    }
  }
} catch (Exception $e) {
  error_log("Auth error: " . $e->getMessage());
  SecurityManager::logSecurityEvent('login_error', 'system', $e->getMessage());
}

$_SESSION['flash'] = 'Login gagal. Email/username atau password salah.';
header('Location: login.php');
exit;