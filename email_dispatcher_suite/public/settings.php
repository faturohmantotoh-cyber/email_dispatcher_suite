<?php
// public/settings.php - Settings dengan keamanan berlapis
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';
require_once __DIR__ . '/../lib/security.php';
ensure_dirs();

// Pastikan user sudah login
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Initialize security
$pdo = DB::conn();
SecurityManager::init($pdo);

$user = $_SESSION['user'];

// Ambil informasi user lengkap dari database (termasuk role)
$stmt = $pdo->prepare("SELECT id, username, email, display_name, role FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$currentUser = $stmt->fetch();

$message = '';
$messageType = '';
$activeTab = $_GET['tab'] ?? 'password';

// Define roles
$ROLES = [
    'admin' => 'Administrator - Akses penuh',
    'user' => 'User Biasa - Bisa mengirim email',
    'viewer' => 'Pembaca - Lihat log saja'
];

// Generate CSRF token
$csrf = SecurityManager::generateCSRFToken();

// Handle password change dengan validasi keamanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeTab === 'password') {
    // CSRF protection
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid. Silakan coba lagi.';
        $messageType = 'error';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'Semua field harus diisi.';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Password baru dan konfirmasi tidak cocok.';
            $messageType = 'error';
        } else {
            // Validate password strength
            $passValidation = SecurityManager::validatePassword($newPassword);
            if (!$passValidation['valid']) {
                $message = $passValidation['message'];
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $dbUser = $stmt->fetch();
                
                if (!$dbUser || !password_verify($currentPassword, $dbUser['password_hash'])) {
                    $message = 'Password lama tidak sesuai.';
                    $messageType = 'error';
                    // Log failed password change attempt
                    SecurityManager::logSecurityEvent('failed_password_change', $user['id'], 'Invalid current password');
                } else {
                    // Check password history - prevent reusing old passwords
                    $historyStmt = $pdo->prepare("
                        SELECT password_hash FROM password_history 
                        WHERE user_id = ? 
                        ORDER BY changed_at DESC LIMIT 5
                    ");
                    $historyStmt->execute([$user['id']]);
                    $passwordHistory = $historyStmt->fetchAll();
                    
                    $passwordReused = false;
                    foreach ($passwordHistory as $oldPassword) {
                        if (password_verify($newPassword, $oldPassword['password_hash'])) {
                            $passwordReused = true;
                            break;
                        }
                    }
                    
                    if ($passwordReused) {
                        $message = 'Password baru tidak boleh sama dengan 5 password terakhir Anda.';
                        $messageType = 'error';
                    } else {
                        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                        
                        // Store old password in history
                        $historyInsert = $pdo->prepare("
                            INSERT INTO password_history (user_id, password_hash, changed_at) 
                            VALUES (?, ?, NOW())
                        ");
                        $historyInsert->execute([$user['id'], $dbUser['password_hash']]);
                        
                        // Update password
                        $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW(), requires_password_change = FALSE WHERE id = ?");
                        if ($updateStmt->execute([$newHash, $user['id']])) {
                            $message = 'Password berhasil diubah! Untuk keamanan, silakan login kembali.';
                            $messageType = 'success';
                            // Log password change
                            SecurityManager::logSecurityEvent('password_changed', $user['id'], 'User changed password');
                            
                            // Invalidate all other sessions
                            session_destroy();
                            header('Location: login.php?password_changed=1');
                            exit;
                        } else {
                            $message = 'Gagal mengubah password. Coba lagi.';
                            $messageType = 'error';
                        }
                    }
                }
            }
        }
    }
}

// Handle add new user (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeTab === 'users' && $currentUser['role'] === 'admin') {
    // CSRF Protection
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid. Silakan coba lagi.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $newUsername = trim($_POST['new_username'] ?? '');
            $newEmail = trim($_POST['new_email'] ?? '');
            $newDisplayName = trim($_POST['new_display_name'] ?? '');
            $newPassword = $_POST['new_user_password'] ?? '';
            $newRole = $_POST['new_role'] ?? 'user';
            
            // Comprehensive validation
            if (empty($newUsername) || empty($newEmail) || empty($newPassword)) {
                $message = 'Username, email, dan password harus diisi.';
                $messageType = 'error';
            } elseif (!SecurityManager::validateEmail($newEmail)) {
                $message = 'Format email tidak valid.';
                $messageType = 'error';
            } elseif (strlen($newUsername) < 3) {
                $message = 'Username minimal 3 karakter.';
                $messageType = 'error';
            } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $newUsername)) {
                $message = 'Username hanya boleh berisi huruf, angka, titik, minus, dan underscore.';
                $messageType = 'error';
            } else {
                // Validate password strength
                $passValidation = SecurityManager::validatePassword($newPassword);
                if (!$passValidation['valid']) {
                    $message = $passValidation['message'];
                    $messageType = 'error';
                } elseif (!in_array($newRole, array_keys($ROLES))) {
                    $message = 'Role tidak valid.';
                    $messageType = 'error';
                } else {
                    try {
                        // Check for duplicate username or email
                        $existingStmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)");
                        $existingStmt->execute([$newUsername, $newEmail]);
                        if ($existingStmt->fetch()) {
                            $message = 'Username atau email sudah terdaftar.';
                            $messageType = 'error';
                        } else {
                            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                            $insertStmt = $pdo->prepare("
                                INSERT INTO users(username, email, display_name, password_hash, role, requires_password_change) 
                                VALUES(?, ?, ?, ?, ?, TRUE)
                            ");
                            $insertStmt->execute([$newUsername, $newEmail, $newDisplayName, $newHash, $newRole]);
                            
                            // Log user creation
                            SecurityManager::logSecurityEvent('user_created', $currentUser['id'], "Created user: $newUsername with role: $newRole");
                            
                            $message = "User '$newUsername' berhasil dibuat dengan role '$newRole'! User harus mengubah password pada login pertama.";
                            $messageType = 'success';
                        }
                    } catch (Exception $e) {
                        $message = 'Gagal membuat user. ' . $e->getMessage();
                        $messageType = 'error';
                        SecurityManager::logSecurityEvent('user_creation_failed', $currentUser['id'], $e->getMessage());
                    }
                }
            }
        }
        
        if ($action === 'delete') {
            $deleteId = (int)($_POST['delete_id'] ?? 0);
            if ($deleteId > 0 && $deleteId !== $currentUser['id']) {
                try {
                    // Get user info before delete for audit log
                    $delStmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
                    $delStmt->execute([$deleteId]);
                    $deletedUser = $delStmt->fetch();
                    
                    $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $deleteStmt->execute([$deleteId]);
                    
                    // Log deletion
                    SecurityManager::logSecurityEvent('user_deleted', $currentUser['id'], "Deleted user: " . $deletedUser['username']);
                    
                    $message = 'User berhasil dihapus.';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Gagal menghapus user. ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = 'Tidak bisa menghapus user ini.';
                $messageType = 'error';
            }
        }
        
        if ($action === 'change_role') {
            $changeId = (int)($_POST['change_id'] ?? 0);
            $changeRole = $_POST['change_role'] ?? 'user';
            
            if ($changeId > 0 && $changeId !== $currentUser['id'] && in_array($changeRole, array_keys($ROLES))) {
                try {
                    // Get user info before change for audit log
                    $getStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                    $getStmt->execute([$changeId]);
                    $userBefore = $getStmt->fetch();
                    
                    $updateRoleStmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $updateRoleStmt->execute([$changeRole, $changeId]);
                    
                    // Log role change
                    SecurityManager::logSecurityEvent('role_changed', $currentUser['id'], "Changed user role from {$userBefore['role']} to $changeRole");
                    
                    $message = 'Role user berhasil diupdate.';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Gagal update role. ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = 'Tidak bisa mengubah role user ini.';
                $messageType = 'error';
            }
        }
        
        if ($action === 'edit') {
            $editId = (int)($_POST['edit_id'] ?? 0);
            $editUsername = trim($_POST['edit_username'] ?? '');
            $editEmail = trim($_POST['edit_email'] ?? '');
            $editDisplayName = trim($_POST['edit_display_name'] ?? '');
            $editPassword = $_POST['edit_password'] ?? '';
            
            if ($editId > 0 && $editId !== $currentUser['id']) {
                // Validate input
                if (empty($editUsername) || empty($editEmail)) {
                    $message = 'Username dan email harus diisi.';
                    $messageType = 'error';
                } elseif (!SecurityManager::validateEmail($editEmail)) {
                    $message = 'Format email tidak valid.';
                    $messageType = 'error';
                } elseif (strlen($editUsername) < 3) {
                    $message = 'Username minimal 3 karakter.';
                    $messageType = 'error';
                } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $editUsername)) {
                    $message = 'Username hanya boleh berisi huruf, angka, titik, minus, dan underscore.';
                    $messageType = 'error';
                } else {
                    try {
                        // Check for duplicate username or email (excluding current user)
                        $existingStmt = $pdo->prepare("
                            SELECT id FROM users 
                            WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?))
                            AND id != ?
                        ");
                        $existingStmt->execute([$editUsername, $editEmail, $editId]);
                        
                        if ($existingStmt->fetch()) {
                            $message = 'Username atau email sudah digunakan user lain.';
                            $messageType = 'error';
                        } else {
                            // Get old user info for audit log
                            $oldStmt = $pdo->prepare("SELECT username, email, display_name FROM users WHERE id = ?");
                            $oldStmt->execute([$editId]);
                            $oldUser = $oldStmt->fetch();
                            
                            // Update user details
                            if (!empty($editPassword)) {
                                // Validate password strength if provided
                                $passValidation = SecurityManager::validatePassword($editPassword);
                                if (!$passValidation['valid']) {
                                    $message = $passValidation['message'];
                                    $messageType = 'error';
                                } else {
                                    $newHash = password_hash($editPassword, PASSWORD_BCRYPT);
                                    $updateStmt = $pdo->prepare("
                                        UPDATE users 
                                        SET username = ?, email = ?, display_name = ?, password_hash = ?, requires_password_change = FALSE, updated_at = NOW() 
                                        WHERE id = ?
                                    ");
                                    $updateStmt->execute([$editUsername, $editEmail, $editDisplayName, $newHash, $editId]);
                                    
                                    // Log changes
                                    SecurityManager::logSecurityEvent('user_edited', $currentUser['id'], 
                                        "Edited user: changed username from {$oldUser['username']} to $editUsername, email updated, password reset");
                                    
                                    $message = 'User berhasil diupdate (dengan password baru).';
                                    $messageType = 'success';
                                }
                            } else {
                                // Update without password change
                                $updateStmt = $pdo->prepare("
                                    UPDATE users 
                                    SET username = ?, email = ?, display_name = ?, updated_at = NOW() 
                                    WHERE id = ?
                                ");
                                $updateStmt->execute([$editUsername, $editEmail, $editDisplayName, $editId]);
                                
                                // Log changes
                                SecurityManager::logSecurityEvent('user_edited', $currentUser['id'], 
                                    "Edited user: changed username from {$oldUser['username']} to $editUsername, email updated");
                                
                                $message = 'User berhasil diupdate.';
                                $messageType = 'success';
                            }
                        }
                    } catch (Exception $e) {
                        $message = 'Gagal mengupdate user. ' . $e->getMessage();
                        $messageType = 'error';
                        SecurityManager::logSecurityEvent('user_edit_failed', $currentUser['id'], $e->getMessage());
                    }
                }
            } else {
                $message = 'Tidak bisa mengedit user ini.';
                $messageType = 'error';
            }
        }
        
        if ($action === 'password_reset') {
            $resetId = (int)($_POST['reset_id'] ?? 0);
            $resetPassword = $_POST['reset_password'] ?? '';
            
            if ($resetId > 0 && $resetId !== $currentUser['id']) {
                // Validate password strength
                $passValidation = SecurityManager::validatePassword($resetPassword);
                if (!$passValidation['valid']) {
                    $message = $passValidation['message'];
                    $messageType = 'error';
                } else {
                    try {
                        // Get user info for audit log
                        $getUserStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                        $getUserStmt->execute([$resetId]);
                        $userToReset = $getUserStmt->fetch();
                        
                        $newHash = password_hash($resetPassword, PASSWORD_BCRYPT);
                        $resetStmt = $pdo->prepare("
                            UPDATE users 
                            SET password_hash = ?, requires_password_change = TRUE, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $resetStmt->execute([$newHash, $resetId]);
                        
                        // Log password reset
                        SecurityManager::logSecurityEvent('password_reset', $currentUser['id'], 
                            "Admin reset password for user: {$userToReset['username']}");
                        
                        $message = 'Password user berhasil di-reset. User harus mengubah password pada login berikutnya.';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Gagal mereset password. ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
            } else {
                $message = 'Tidak bisa mereset password user ini.';
                $messageType = 'error';
            }
        }
    }
}

// Ambil daftar semua users untuk admin
$allUsers = [];
if ($currentUser['role'] === 'admin') {
    $result = $pdo->query("SELECT id, username, email, display_name, role, created_at FROM users ORDER BY created_at DESC");
    $allUsers = $result->fetchAll();
}

// Handle maintenance - cleanup old jobs (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cleanup_database') {
    // CSRF protection
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid. Silakan coba lagi.';
        $messageType = 'error';
    } elseif ($currentUser['role'] !== 'admin') {
        $message = 'Hanya admin yang dapat menjalankan maintenance.';
        $messageType = 'error';
    } else {
        $adminPassword = $_POST['admin_password'] ?? '';
        
        if (empty($adminPassword)) {
            $message = 'Masukkan password untuk konfirmasi.';
            $messageType = 'error';
        } else {
            // Verify admin password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            $adminUser = $stmt->fetch();
            
            if (!$adminUser || !password_verify($adminPassword, $adminUser['password_hash'])) {
                $message = 'Password tidak sesuai. Cleanup dibatalkan.';
                $messageType = 'error';
                SecurityManager::logSecurityEvent('failed_maintenance_attempt', $currentUser['id'], 'Invalid password for cleanup');
            } else {
                try {
                    // Execute cleanup stored procedure
                    $cleanupStmt = $pdo->prepare("CALL cleanup_old_jobs(90)");
                    $cleanupStmt->execute();
                    
                    // Get the result (number of items deleted)
                    $result = $cleanupStmt->fetchAll();
                    
                    // Log the maintenance action
                    SecurityManager::logSecurityEvent('database_cleanup', $currentUser['id'], 'Executed cleanup_old_jobs(90)');
                    
                    $message = '✅ Database cleanup berhasil dilakukan! Email lama (>90 hari) telah dihapus.';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Gagal menjalankan cleanup: ' . $e->getMessage();
                    $messageType = 'error';
                    SecurityManager::logSecurityEvent('maintenance_failed', $currentUser['id'], $e->getMessage());
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Pengaturan Pengguna</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:0;background:#f7f7fb;color:#222;font-size:15px;letter-spacing:-0.01em}
header{background:#0d6efd;color:#fff;padding:16px}
main{padding:20px;max-width:1100px;margin:0 auto}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:16px}
.card h3{margin:0;padding:12px 16px;border-bottom:1px solid #e5e7eb}
.card .body{padding:16px}

/* --- NAV dengan ikon --- */
.nav{
  display:flex;
  gap:12px;
  flex-wrap:wrap;
  margin-top:8px;
}
.nav a{
  display:inline-flex;
  align-items:center;
  gap:8px;
  margin-right:0;
  color:#ffffff;            /* putih agar kontras di header */
  text-decoration:none;
  font-weight:500;
  line-height:1;
  padding:6px 8px;
  border-radius:6px;
  transition:background .15s ease, opacity .15s ease;
}
.nav a:hover{
  background:rgba(255,255,255,.12);
  text-decoration:none;
}
.nav .icon{
  display:inline-flex;
  width:18px;
  height:18px;
}
.nav .icon svg{
  width:18px;height:18px;
  fill:currentColor;
}

.btn{display:inline-block;background:#0d6efd;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none;border:0;cursor:pointer}
.btn.secondary{background:#6b7280}
.btn.danger{background:#dc2626}

label{display:block;margin-top:8px;font-weight:500}
input{padding:8px;border:1px solid #d1d5db;border-radius:6px;width:100%;box-sizing:border-box;margin-top:4px}

.alert{padding:12px 16px;border-radius:6px;margin-bottom:16px}
.alert.success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}
.alert.error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}

.small{color:#6b7280;font-size:12px;margin-top:4px}

/* Tabs */
.tabs{display:flex;gap:4px;border-bottom:2px solid #e5e7eb;margin-bottom:16px}
.tabs button{background:none;border:none;border-bottom:3px solid transparent;cursor:pointer;padding:12px 16px;font-weight:500;color:#6b7280;transition:all .15s}
.tabs button.active{color:#0d6efd;border-bottom-color:#0d6efd}

.tab-content{display:none}
.tab-content.active{display:block}

/* User table */
.user-table{width:100%;border-collapse:collapse;margin-top:12px}
.user-table th,.user-table td{padding:12px;text-align:left;border-bottom:1px solid #e5e7eb}
.user-table th{background:#f3f4f6;font-weight:600}
.user-table tr:hover{background:#f9fafb}

.role-badge{display:inline-block;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500}
.role-badge.admin{background:#fecaca;color:#991b1b}
.role-badge.user{background:#bfdbfe;color:#1e3a8a}
.role-badge.viewer{background:#d1d5db;color:#374151}

.form-row{display:flex;gap:12px;margin-top:8px}
.form-row input,.form-row select{flex:1}

button.btn.sm{padding:6px 10px;font-size:12px}

/* Modal Styles - Improved Stability */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  align-items: center;
  justify-content: center;
  overflow: auto;
  padding: 20px;
  box-sizing: border-box;
}

.modal.active {
  display: flex !important;
}

.modal-content {
  background-color: #fff;
  padding: 24px;
  border-radius: 8px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  max-width: 500px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  position: relative;
  animation: slideInUp 0.3s ease-out;
  pointer-events: auto;  /* Important: allow interactions inside modal */
}

.modal-content input,
.modal-content label,
.modal-content button,
.modal-content select {
  pointer-events: auto;
}

@keyframes slideInUp {
  from {
    transform: translateY(50px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-content h3 {
  margin: 0 0 16px 0;
  padding-right: 30px;
  color: #222;
}

.modal-content p {
  margin: 0 0 16px 0;
  color: #666;
}

.modal-close {
  position: absolute;
  right: 12px;
  top: 12px;
  font-size: 28px;
  line-height: 1;
  cursor: pointer;
  color: #999;
  border: none;
  background: none;
  padding: 4px 8px;
  width: auto;
  height: auto;
  display: block;
  transition: color 0.2s;
  pointer-events: auto;
}

.modal-close:hover {
  color: #333;
}

.modal-close:hover {
  color: #333;
}

/* Page Transition Animations */
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeOut {
  from { opacity: 1; transform: translateY(0); }
  to { opacity: 0; transform: translateY(-20px); }
}
@keyframes overlayIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.page-transition {
  position: fixed;
  inset: 0;
  background: linear-gradient(135deg, #0052CC 0%, #003fa3 100%);
  z-index: 9999;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.page-transition.active {
  opacity: 1;
  pointer-events: auto;
  animation: overlayIn 0.3s ease-out;
}

main { animation: fadeInUp 0.6s ease-out 0.1s both; }
main.transitioning { animation: fadeOut 0.3s ease-out forwards; }

</style>
</head>
<body>
<header>
  <h2 style="margin:0 0 8px 0;">Pengaturan Pengguna</h2>
  <div class="nav">
    <a href="index.php">
      <span class="icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
          <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
        </svg>
      </span>
      Dashboard
    </a>
    <a href="logout.php">
      <span class="icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
          <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
        </svg>
      </span>
      Logout
    </a>
  </div>
</header>

<main>
  <div class="card">
    <h3>Pengaturan Pengguna & Sistem</h3>
    <div class="body">
      <?php if (!empty($message)): ?>
      <div class="alert <?= e($messageType) ?>">
        <?= e($message) ?>
      </div>
      <?php endif; ?>

      <div style="margin-bottom:16px;padding:12px;background:#f0f5ff;border-radius:6px;border-left:4px solid #0d6efd;">
        <strong>User:</strong> <?= e($currentUser['display_name'] ?? $currentUser['username']) ?> 
        <span class="role-badge <?= e($currentUser['role']) ?>"><?= e($ROLES[$currentUser['role']] ?? 'Unknown') ?></span>
        <br>
        <small style="color:#666;">Email: <?= e($currentUser['email']) ?></small>
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <button type="button" class="tab-btn <?= $activeTab === 'password' ? 'active' : '' ?>" onclick="switchTab('password')">🔐 Ubah Password</button>
        <?php if ($currentUser['role'] === 'admin'): ?>
        <button type="button" class="tab-btn <?= $activeTab === 'users' ? 'active' : '' ?>" onclick="switchTab('users')">👥 Kelola User</button>
        <button type="button" class="tab-btn <?= $activeTab === 'maintenance' ? 'active' : '' ?>" onclick="switchTab('maintenance')">🔧 Maintenance</button>
        <?php endif; ?>
      </div>

      <!-- Tab 1: Password -->
      <div id="password" class="tab-content <?= $activeTab === 'password' ? 'active' : '' ?>">
        <h4>Ubah Password</h4>
        <form method="post" action="?tab=password" style="max-width:500px;">
          <label for="current_password">Password Lama (saat ini)
            <input type="password" id="current_password" name="current_password" required minlength="6" placeholder="Masukkan password lama Anda">
            <span class="small">Perlu diverifikasi sebelum mengubah password.</span>
          </label>

          <label for="new_password" style="margin-top:16px;">Password Baru
            <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="Minimal 6 karakter">
            <span class="small">Gunakan kombinasi huruf, angka, dan simbol untuk keamanan maksimal.</span>
          </label>

          <label for="confirm_password" style="margin-top:16px;">Konfirmasi Password Baru
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Ulangi password baru">
            <span class="small">Harus sama dengan password baru di atas.</span>
          </label>

          <div style="margin-top:20px;display:flex;gap:8px;">
            <button type="submit" class="btn">💾 Simpan Password Baru</button>
            <a href="index.php" class="btn secondary">⟵ Kembali</a>
          </div>
        </form>
      </div>

      <!-- Tab 2: User Management (Admin Only) -->
      <?php if ($currentUser['role'] === 'admin'): ?>
      <div id="users" class="tab-content <?= $activeTab === 'users' ? 'active' : '' ?>">
        <h4>Kelola User</h4>

        <!-- Add New User Form -->
        <div style="background:#f9fafb;padding:16px;border-radius:6px;margin-bottom:24px;">
          <h5>➕ Tambah User Baru</h5>
          <form method="post" action="?tab=users" style="max-width:700px;">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="form-row">
              <div style="flex:1;">
                <label>Username (untuk login)
                  <input type="text" name="new_username" required placeholder="contoh: user@company.com">
                </label>
              </div>
              <div style="flex:1;">
                <label>Email
                  <input type="email" name="new_email" required placeholder="email@company.com">
                </label>
              </div>
            </div>

            <div class="form-row">
              <div style="flex:1;">
                <label>Nama Lengkap
                  <input type="text" name="new_display_name" placeholder="Nama Tampilan">
                </label>
              </div>
              <div style="flex:1;">
                <label>Role/Otorisasi
                  <select name="new_role" required>
                    <?php foreach ($ROLES as $role => $desc): ?>
                    <option value="<?= e($role) ?>"><?= e($desc) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
              </div>
            </div>

            <label>Password Awal (minimal 6 karakter)
              <input type="password" name="new_user_password" required minlength="6" placeholder="Password yang user gunakan untuk login">
            </label>

            <div style="margin-top:12px;">
              <button type="submit" class="btn">✓ Buat User Baru</button>
            </div>
          </form>
        </div>

        <!-- List Users -->
        <h5>Daftar User Terdaftar</h5>
        <table class="user-table">
          <thead>
            <tr>
              <th>Username</th>
              <th>Email</th>
              <th>Nama</th>
              <th>Role</th>
              <th>Dibuat</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allUsers as $u): ?>
            <tr>
              <td><strong><?= e($u['username']) ?></strong></td>
              <td><?= e($u['email']) ?></td>
              <td><?= e($u['display_name'] ?? '-') ?></td>
              <td>
                <span class="role-badge <?= e($u['role']) ?>">
                  <?php 
                  $roleDesc = $ROLES[$u['role']] ?? 'Unknown';
                  echo strpos($roleDesc, ' - ') ? explode(' - ', $roleDesc)[0] : $roleDesc;
                  ?>
                </span>
              </td>
              <td><small><?= date('d.m.Y H:i', strtotime($u['created_at'])) ?></small></td>
              <td>
                <?php if ($u['id'] !== $currentUser['id']): ?>
                <!-- Edit Button -->
                <button type="button" class="btn btn.sm" style="padding:4px 8px;font-size:12px;background:#0d6efd;" onclick="openEditModal(<?= (int)$u['id'] ?>, '<?= e($u['username']) ?>', '<?= e($u['email']) ?>', '<?= e($u['display_name']) ?>')">
                  ✏️ Edit
                </button>
                
                <!-- Role Change -->
                <form method="post" action="?tab=users" style="display:inline;margin-left:4px;">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="change_role">
                  <input type="hidden" name="change_id" value="<?= (int)$u['id'] ?>">
                  <select name="change_role" onchange="this.form.submit()" style="padding:4px;font-size:12px;border:1px solid #d1d5db;border-radius:4px;">
                    <?php foreach ($ROLES as $role => $desc): ?>
                    <option value="<?= e($role) ?>" <?= $u['role'] === $role ? 'selected' : '' ?>>
                      <?php echo strpos($desc, ' - ') ? explode(' - ', $desc)[0] : $desc; ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </form>
                
                <!-- Reset Password Button -->
                <button type="button" class="btn btn.sm" style="padding:4px 8px;font-size:12px;background:#f59e0b;margin-left:4px;" onclick="openResetPasswordModal(<?= (int)$u['id'] ?>, '<?= e($u['username']) ?>')">
                  🔄 Reset Pass
                </button>
                
                <!-- Delete Button -->
                <form method="post" action="?tab=users" style="display:inline;margin-left:4px;">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="delete_id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" class="btn btn.sm danger" onclick="return confirm('Yakin hapus user ini?')" style="padding:4px 8px;font-size:12px;background:#dc2626;color:#fff;border:0;border-radius:4px;cursor:pointer;">🗑 Hapus</button>
                </form>
                <?php else: ?>
                <small style="color:#999;">(User saat ini)</small>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div style="margin-top:24px;">
          <a href="index.php" class="btn secondary">⟵ Kembali</a>
        </div>
      </div>

      <!-- Tab 3: Maintenance (Admin Only) -->
      <div id="maintenance" class="tab-content <?= $activeTab === 'maintenance' ? 'active' : '' ?>">
        <h4>🔧 Database Maintenance</h4>
        <p style="color:#666;margin-bottom:16px;">Jalankan pemeliharaan berkala untuk menjaga database tetap cepat dan responsif.</p>
        
        <div style="background:#f3f4f6;border:1px solid #d1d5db;border-radius:6px;padding:16px;margin-bottom:20px;">
          <h5 style="margin-top:0;">🗑️ Hapus Email Lama (Cleanup)</h5>
          <p style="color:#555;font-size:14px;">
            Menghapus job email yang sudah selesai dan berusia lebih dari 90 hari.<br>
            <strong>Efek:</strong> Membuat database lebih kecil dan query lebih cepat.
          </p>
          
          <div style="background:#fff;border:1px solid #d1d5db;border-radius:4px;padding:12px;margin:12px 0;font-size:13px;">
            <div style="display:flex;justify-content:space-between;margin:6px 0;">
              <span>Status Database:</span>
              <span id="dbStatusText" style="font-weight:bold;color:#059669;">Siap Dibersihkan</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin:6px 0;">
              <span>Total Email Jobs:</span>
              <span id="dbJobCount" style="font-weight:bold;">Loading...</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin:6px 0;">
              <span>Job Lama (>90 hari):</span>
              <span id="dbOldJobCount" style="font-weight:bold;color:#dc2626;">Loading...</span>
            </div>
          </div>
          
          <button type="button" class="btn" style="background:#059669;border:0;padding:10px 20px;" onclick="openMaintenanceModal()">
            ✓ Jalankan Cleanup
          </button>
        </div>

        <div style="background:#f0fdf4;border-left:4px solid #059669;padding:12px;border-radius:4px;">
          <strong>💡 Catatan:</strong> Proses cleanup dilakukan secara aman. Hanya job yang sudah selesai (status: completed/failed) yang dihapus.
        </div>
        
        <div style="margin-top:24px;">
          <a href="index.php" class="btn secondary">⟵ Kembali</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<script>
function switchTab(tabName) {
  // Hide all tabs
  document.querySelectorAll('.tab-content').forEach(el => {
    el.classList.remove('active');
  });
  document.querySelectorAll('.tab-btn').forEach(el => {
    el.classList.remove('active');
  });
  
  // Show selected tab
  const tab = document.getElementById(tabName);
  if (tab) {
    tab.classList.add('active');
  }
  
  // Set active button
  event.target.classList.add('active');
  
  // Update URL without reload
  const url = new URL(window.location);
  url.searchParams.set('tab', tabName);
  window.history.replaceState({}, '', url);
}

// Modal functions for Edit User - SIMPLIFIED for better stability
function openEditModal(userId, username, email, displayName) {
  const modal = document.getElementById('editUserModal');
  if (!modal) return;
  
  // Set form values
  document.getElementById('editUserId').value = userId;
  document.getElementById('editUsername').value = username;
  document.getElementById('editEmail').value = email;
  document.getElementById('editDisplayName').value = displayName;
  document.getElementById('editPasswordField').value = '';
  
  // Show modal
  modal.classList.add('active');
}

function closeEditModal() {
  const modal = document.getElementById('editUserModal');
  if (modal) {
    modal.classList.remove('active');
  }
}

function openResetPasswordModal(userId, username) {
  const modal = document.getElementById('resetPasswordModal');
  if (!modal) return;
  
  // Set form values
  document.getElementById('resetUserId').value = userId;
  document.getElementById('resetUsername').textContent = username;
  document.getElementById('resetPasswordField').value = '';
  
  // Show modal
  modal.classList.add('active');
}

function closeResetPasswordModal() {
  const modal = document.getElementById('resetPasswordModal');
  if (modal) {
    modal.classList.remove('active');
  }
}

function openMaintenanceModal() {
  const modal = document.getElementById('maintenanceModal');
  if (!modal) return;
  
  // Clear the password field
  document.getElementById('adminPassword').value = '';
  
  // Show modal
  modal.classList.add('active');
}

function closeMaintenanceModal() {
  const modal = document.getElementById('maintenanceModal');
  if (modal) {
    modal.classList.remove('active');
  }
}

// Handle background clicks more carefully
document.addEventListener('click', function(event) {
  const editModal = document.getElementById('editUserModal');
  const resetModal = document.getElementById('resetPasswordModal');
  const maintenanceModal = document.getElementById('maintenanceModal');
  
  // Only close if clicking EXACTLY on the modal background, not on modal-content
  // Check if the click is on the modal but not on its content
  if (event.target === editModal) {
    closeEditModal();
    return;
  }
  
  if (event.target === resetModal) {
    closeResetPasswordModal();
    return;
  }
  
  if (event.target === maintenanceModal) {
    closeMaintenanceModal();
    return;
  }
}, false);

// Initialize form handlers when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  // Edit User Form
  const editForm = document.querySelector('#editUserModal form');
  if (editForm) {
    editForm.addEventListener('submit', function(e) {
      // Don't prevent default - let it submit!
      // Just validate first
      const username = document.getElementById('editUsername').value.trim();
      const email = document.getElementById('editEmail').value.trim();
      
      if (!username || !email) {
        e.preventDefault();
        alert('❌ Username dan email harus diisi!');
        return false;
      }
      
      if (username.length < 3) {
        e.preventDefault();
        alert('❌ Username minimal 3 karakter!');
        return false;
      }
      
      // All good - form will submit normally
      return true;
    });
  }
  
  // Reset Password Form
  const resetForm = document.querySelector('#resetPasswordModal form');
  if (resetForm) {
    resetForm.addEventListener('submit', function(e) {
      const password = document.getElementById('resetPasswordField').value.trim();
      
      if (!password || password.length < 6) {
        e.preventDefault();
        alert('❌ Password minimal 6 karakter!');
        return false;
      }
      
      // All good - form will submit normally
      return true;
    });
  }
  
  // Maintenance Form
  const maintenanceForm = document.querySelector('#maintenanceModal form');
  if (maintenanceForm) {
    maintenanceForm.addEventListener('submit', function(e) {
      const password = document.getElementById('adminPassword').value.trim();
      
      if (!password) {
        e.preventDefault();
        alert('❌ Masukkan password untuk konfirmasi!');
        return false;
      }
      
      // Confirm action
      if (!confirm('⚠️ Ini akan menghapus email jobs lama (>90 hari) secara permanen.\n\nLanjutkan?')) {
        e.preventDefault();
        return false;
      }
      
      // All good - form will submit normally
      return true;
    });
  }
});

// Page Transition Animations
function initPageTransitions() {
  const navLinks = document.querySelectorAll('.sidebar-nav a, .quick-actions a, main a[href$=".php"]');
  navLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      if (!href || href.startsWith('#') || href.startsWith('http')) return;
      e.preventDefault();
      const overlay = document.getElementById('pageTransition');
      const mainContent = document.querySelector('main') || document.querySelector('.main-content');
      
      if (overlay) {
        overlay.classList.add('active');
        if (mainContent) mainContent.classList.add('transitioning');
        setTimeout(() => { window.location.href = href; }, 300);
      } else {
        window.location.href = href;
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', initPageTransitions);
window.addEventListener('load', function() {
  const overlay = document.getElementById('pageTransition');
  const mainContent = document.querySelector('main') || document.querySelector('.main-content');
  if (overlay) {
    overlay.classList.remove('active');
    if (mainContent) mainContent.classList.remove('transitioning');
  }
});
</script>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
  <div class="modal-content">
    <button type="button" class="modal-close" title="Tutup" onclick="closeEditModal();">&times;</button>
    <h3>✏️ Edit User</h3>
    <form method="post" action="?tab=users">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" id="editUserId" name="edit_id" value="">
      
      <div style="margin-bottom:12px;">
        <label for="editUsername">Username
          <input type="text" id="editUsername" name="edit_username" required minlength="3" placeholder="Username untuk login" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; margin-top:4px; box-sizing:border-box;">
          <span class="small">Minimal 3 karakter, alphanumeric + . _ -</span>
        </label>
      </div>
      
      <div style="margin-bottom:12px;">
        <label for="editEmail">Email
          <input type="email" id="editEmail" name="edit_email" required placeholder="email@company.com" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; margin-top:4px; box-sizing:border-box;">
        </label>
      </div>
      
      <div style="margin-bottom:12px;">
        <label for="editDisplayName">Nama Lengkap
          <input type="text" id="editDisplayName" name="edit_display_name" placeholder="Nama tampilan (opsional)" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; margin-top:4px; box-sizing:border-box;">
        </label>
      </div>
      
      <div style="margin-bottom:12px;">
        <label for="editPasswordField">Password Baru (Kosongkan jika tidak ingin ubah)
          <input type="password" id="editPasswordField" name="edit_password" minlength="6" placeholder="Minimal 6 karakter (opsional)" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; margin-top:4px; box-sizing:border-box;">
          <span class="small">Jika diisi, akan reset password user.</span>
        </label>
      </div>
      
      <div style="margin-top:20px;display:flex;gap:8px;">
        <button type="submit" class="btn" style="flex:1;">✓ Simpan Perubahan</button>
        <button type="button" class="btn secondary" style="flex:1;" onclick="closeEditModal();">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal">
  <div class="modal-content">
    <button type="button" class="modal-close" title="Tutup" onclick="closeResetPasswordModal();">&times;</button>
    <h3>🔄 Reset Password User</h3>
    <p>User: <strong id="resetUsername"></strong></p>
    <form method="post" action="?tab=users">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="password_reset">
      <input type="hidden" id="resetUserId" name="reset_id" value="">
      
      <div style="margin-bottom:12px;">
        <label for="resetPasswordField">Password Baru (Minimal 6 karakter)
          <input type="password" id="resetPasswordField" name="reset_password" required minlength="6" placeholder="Masukkan password baru" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; margin-top:4px; box-sizing:border-box;">
          <span class="small">User akan diminta ubah password pada login berikutnya.</span>
        </label>
      </div>
      
      <div style="margin-top:20px;display:flex;gap:8px;">
        <button type="submit" class="btn danger" style="flex:1;background:#dc2626;">⚠️ Reset Password</button>
        <button type="button" class="btn secondary" style="flex:1;" onclick="closeResetPasswordModal();">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Maintenance Modal -->
<div id="maintenanceModal" class="modal">
  <div class="modal-content">
    <button type="button" class="modal-close" title="Tutup" onclick="closeMaintenanceModal();">&times;</button>
    <h3>🔧 Database Cleanup Confirmation</h3>
    <p style="color:#666;">Masukkan password Anda untuk mengkonfirmasi database cleanup.</p>
    <p style="background:#fef3c7;border:1px solid #fcd34d;border-radius:4px;padding:12px;font-size:13px;">
      <strong>⚠️ Perhatian:</strong> Ini akan menghapus semua email jobs yang sudah selesai dan berusia lebih dari 90 hari. Aksi ini tidak dapat dibatalkan!
    </p>
    <form method="post" action="?tab=maintenance">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="cleanup_database">
      
      <div style="margin-bottom:12px;">
        <label for="adminPassword">Password Anda
          <input type="password" id="adminPassword" name="admin_password" required placeholder="Masukkan password Anda" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; margin-top:4px; box-sizing:border-box;">
        </label>
      </div>
      
      <div style="margin-top:20px;display:flex;gap:8px;">
        <button type="submit" class="btn" style="flex:1;background:#059669;">✓ Jalankan Cleanup</button>
        <button type="button" class="btn secondary" style="flex:1;" onclick="closeMaintenanceModal();">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Page Transition Overlay -->
<div class="page-transition" id="pageTransition"></div>

</body>
</html>
