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
                // Require admin password verification
                require_admin_password('admin_password', $pdo, 'settings.php?tab=users');
                
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
    
    // Get current email sending mode from database
    $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE `key` = 'email_sending_mode'");
    $stmt->execute();
    $modeResult = $stmt->fetch();
    $currentEmailMode = $modeResult['value'] ?? EMAIL_SENDING_MODE;
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

// Handle email sending mode configuration (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_email_mode') {
    // CSRF protection
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid. Silakan coba lagi.';
        $messageType = 'error';
    } elseif ($currentUser['role'] !== 'admin') {
        $message = 'Hanya admin yang dapat mengubah konfigurasi email.';
        $messageType = 'error';
    } else {
        $emailMode = $_POST['email_sending_mode'] ?? 'outlook_com';
        
        // Validate mode
        if (!in_array($emailMode, ['outlook_com', 'graph_api', 'smtp', 'client_engine'])) {
            $message = 'Mode pengiriman email tidak valid.';
            $messageType = 'error';
        } else {
            try {
                // Check if setting exists
                $stmt = $pdo->prepare("SELECT id FROM system_settings WHERE `key` = 'email_sending_mode'");
                $stmt->execute();
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update existing setting
                    $stmt = $pdo->prepare("UPDATE system_settings SET value = ?, updated_at = NOW() WHERE `key` = 'email_sending_mode'");
                    $stmt->execute([$emailMode]);
                } else {
                    // Insert new setting
                    $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, value, type, description) VALUES ('email_sending_mode', ?, 'string', 'Email sending mode: outlook_com, graph_api, or smtp')");
                    $stmt->execute([$emailMode]);
                }
                
                SecurityManager::logSecurityEvent('email_mode_changed', $currentUser['id'], "Email sending mode changed to: $emailMode");
                
                $modeNames = [
                    'outlook_com' => 'Outlook COM',
                    'graph_api' => 'Microsoft Graph API',
                    'smtp' => 'SMTP Direct',
                    'client_engine' => 'Client Engine (Local Outlook)'
                ];
                $message = '✅ Mode pengiriman email berhasil diubah ke ' . ($modeNames[$emailMode] ?? $emailMode);
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Gagal mengubah mode pengiriman email: ' . $e->getMessage();
                $messageType = 'error';
                SecurityManager::logSecurityEvent('email_mode_change_failed', $currentUser['id'], $e->getMessage());
            }
        }
    }
}

// Handle Client Engine token generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_engine_token') {
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid. Silakan coba lagi.';
        $messageType = 'error';
    } else {
        try {
            $tokenName = $_POST['token_name'] ?? 'Default';
            $tokenDescription = $_POST['token_description'] ?? '';
            $token = bin2hex(random_bytes(32)); // 64 character token
            
            $stmt = $pdo->prepare("
                INSERT INTO user_api_tokens (user_id, token, token_name, description, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$currentUser['id'], $token, $tokenName, $tokenDescription]);
            
            SecurityManager::logSecurityEvent('engine_token_generated', $currentUser['id'], "Token generated: $tokenName");
            
            $message = '✅ Token berhasil dibuat. Copy dan simpan token ini dengan aman: <strong>' . e($token) . '</strong><br><small>Token hanya ditampilkan sekali!</small>';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Gagal membuat token: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Handle Client Engine token revocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_engine_token') {
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid. Silakan coba lagi.';
        $messageType = 'error';
    } else {
        try {
            $tokenId = intval($_POST['token_id'] ?? 0);
            
            // Verify token belongs to current user
            $stmt = $pdo->prepare("SELECT id FROM user_api_tokens WHERE id = ? AND user_id = ?");
            $stmt->execute([$tokenId, $currentUser['id']]);
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE user_api_tokens SET is_active = 0 WHERE id = ?");
                $stmt->execute([$tokenId]);
                
                SecurityManager::logSecurityEvent('engine_token_revoked', $currentUser['id'], "Token ID: $tokenId revoked");
                
                $message = '✅ Token berhasil di-nonaktifkan.';
                $messageType = 'success';
            } else {
                $message = 'Token tidak ditemukan atau bukan milik Anda.';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = 'Gagal menonaktifkan token: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get user's API tokens
$userTokens = [];
if ($currentUser) {
    $stmt = $pdo->prepare("
        SELECT id, token_name, description, is_active, last_used_at, created_at, ip_address
        FROM user_api_tokens
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$currentUser['id']]);
    $userTokens = $stmt->fetchAll();
}

// Handle SMTP configuration (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_smtp_config') {
    // CSRF protection
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid. Silakan coba lagi.';
        $messageType = 'error';
    } elseif ($currentUser['role'] !== 'admin') {
        $message = 'Hanya admin yang dapat mengubah konfigurasi SMTP.';
        $messageType = 'error';
    } else {
        try {
            $smtpSettings = [
                'smtp_host' => $_POST['smtp_host'] ?? '',
                'smtp_port' => $_POST['smtp_port'] ?? '587',
                'smtp_username' => $_POST['smtp_username'] ?? '',
                'smtp_password' => $_POST['smtp_password'] ?? '',
                'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
                'smtp_from_name' => $_POST['smtp_from_name'] ?? 'Email Dispatcher'
            ];
            
            foreach ($smtpSettings as $key => $value) {
                $stmt = $pdo->prepare("SELECT id FROM system_settings WHERE `key` = ?");
                $stmt->execute([$key]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $stmt = $pdo->prepare("UPDATE system_settings SET value = ?, updated_at = NOW() WHERE `key` = ?");
                    $stmt->execute([$value, $key]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, value, type, description) VALUES (?, ?, 'string', 'SMTP configuration')");
                    $stmt->execute([$key, $value]);
                }
            }
            
            SecurityManager::logSecurityEvent('smtp_config_updated', $currentUser['id'], 'SMTP configuration updated');
            
            $message = '✅ Konfigurasi SMTP berhasil disimpan.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Gagal menyimpan konfigurasi SMTP: ' . $e->getMessage();
            $messageType = 'error';
            SecurityManager::logSecurityEvent('smtp_config_failed', $currentUser['id'], $e->getMessage());
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
        <button type="button" class="tab-btn <?= $activeTab === 'email' ? 'active' : '' ?>" onclick="switchTab('email')">📧 Konfigurasi Email</button>
        <button type="button" class="tab-btn <?= $activeTab === 'maintenance' ? 'active' : '' ?>" onclick="switchTab('maintenance')">🔧 Maintenance</button>
        <button type="button" class="tab-btn <?= $activeTab === 'security' ? 'active' : '' ?>" onclick="switchTab('security')">🛡️ Keamanan & Deliverability</button>
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
                <button type="button" class="btn btn.sm danger" onclick="confirmDeleteUser(<?= (int)$u['id'] ?>, '<?= e($u['username']) ?>')" style="padding:4px 8px;font-size:12px;background:#dc2626;color:#fff;border:0;border-radius:4px;cursor:pointer;">🗑 Hapus</button>
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

      <!-- Tab 3: Email Configuration (Admin Only) -->
      <div id="email" class="tab-content <?= $activeTab === 'email' ? 'active' : '' ?>">
        <h4>📧 Konfigurasi Pengiriman Email</h4>
        <p style="color:#666;margin-bottom:16px;">Pilih metode pengiriman email yang akan digunakan oleh sistem.</p>
        
        <form method="post" action="?tab=email">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="save_email_mode">
          
          <style>
            .email-mode-card {
              position: relative;
              display: flex;
              align-items: flex-start;
              gap: 16px;
              padding: 20px;
              background: #fff;
              border: 2px solid #e5e7eb;
              border-radius: 12px;
              cursor: pointer;
              transition: all 0.2s ease;
            }
            .email-mode-card:hover {
              border-color: #93c5fd;
              box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
              transform: translateY(-2px);
            }
            .email-mode-card.selected {
              border-color: #3b82f6;
              background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
              box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
            }
            .email-mode-icon {
              width: 56px;
              height: 56px;
              border-radius: 12px;
              display: flex;
              align-items: center;
              justify-content: center;
              font-size: 28px;
              flex-shrink: 0;
            }
            .email-mode-card.outlook .email-mode-icon {
              background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            }
            .email-mode-card.graph .email-mode-icon {
              background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
            }
            .email-mode-card.smtp .email-mode-icon {
              background: linear-gradient(135deg, #dcfce7 0%, #86efac 100%);
            }
            .email-mode-card.client-engine .email-mode-icon {
              background: linear-gradient(135deg, #f3e8ff 0%, #c4b5fd 100%);
            }
            .email-mode-card.client-engine.selected {
              border-color: #8b5cf6;
              background: linear-gradient(135deg, #f3e8ff 0%, #ede9fe 100%);
              box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
            }
            .email-mode-radio {
              position: absolute;
              top: 20px;
              right: 20px;
              width: 24px;
              height: 24px;
              border: 2px solid #d1d5db;
              border-radius: 50%;
              cursor: pointer;
              transition: all 0.2s ease;
            }
            .email-mode-radio:checked {
              border-color: #3b82f6;
              background: #3b82f6;
            }
            .email-mode-radio:checked::after {
              content: '✓';
              position: absolute;
              top: 50%;
              left: 50%;
              transform: translate(-50%, -50%);
              color: white;
              font-size: 12px;
              font-weight: bold;
            }
            .email-mode-title {
              font-weight: 700;
              font-size: 16px;
              color: #1f2937;
              margin-bottom: 8px;
            }
            .email-mode-desc {
              color: #6b7280;
              font-size: 14px;
              line-height: 1.6;
            }
            .email-mode-features {
              margin-top: 12px;
              display: flex;
              flex-direction: column;
              gap: 4px;
            }
            .email-mode-feature {
              display: flex;
              align-items: center;
              gap: 8px;
              font-size: 13px;
            }
            .email-mode-feature.pros {
              color: #059669;
            }
            .email-mode-feature.cons {
              color: #dc2626;
            }
          </style>
          
          <div style="display:grid;gap:16px;">
            <!-- Outlook COM Card -->
            <label class="email-mode-card outlook <?= $currentEmailMode === 'outlook_com' ? 'selected' : '' ?>">
              <input type="radio" name="email_sending_mode" value="outlook_com" <?= $currentEmailMode === 'outlook_com' ? 'checked' : '' ?> class="email-mode-radio">
              <div class="email-mode-icon">🖥️</div>
              <div>
                <div class="email-mode-title">Outlook COM</div>
                <div class="email-mode-desc">Menggunakan Outlook desktop application di server untuk mengirim email.</div>
                <div class="email-mode-features">
                  <div class="email-mode-feature pros">
                    <span>✓</span> Tidak perlu setup Azure AD
                  </div>
                  <div class="email-mode-feature cons">
                    <span>⚠</span> Outlook di server harus ON
                  </div>
                  <div class="email-mode-feature cons">
                    <span>⚠</span> Sent items muncul di Outlook server
                  </div>
                </div>
              </div>
            </label>
            
            <!-- Graph API Card -->
            <label class="email-mode-card graph <?= $currentEmailMode === 'graph_api' ? 'selected' : '' ?>">
              <input type="radio" name="email_sending_mode" value="graph_api" <?= $currentEmailMode === 'graph_api' ? 'checked' : '' ?> class="email-mode-radio">
              <div class="email-mode-icon">☁️</div>
              <div>
                <div class="email-mode-title">Microsoft Graph API</div>
                <div class="email-mode-desc">Mengirim email langsung melalui Microsoft Graph API (Office 365).</div>
                <div class="email-mode-features">
                  <div class="email-mode-feature pros">
                    <span>✓</span> Tidak perlu Outlook di server
                  </div>
                  <div class="email-mode-feature pros">
                    <span>✓</span> Sent items di mailbox user masing-masing
                  </div>
                  <div class="email-mode-feature cons">
                    <span>⚠</span> Perlu setup Azure AD dan Office 365
                  </div>
                </div>
              </div>
            </label>
            
            <!-- SMTP Card -->
            <label class="email-mode-card smtp <?= $currentEmailMode === 'smtp' ? 'selected' : '' ?>">
              <input type="radio" name="email_sending_mode" value="smtp" <?= $currentEmailMode === 'smtp' ? 'checked' : '' ?> class="email-mode-radio">
              <div class="email-mode-icon">📧</div>
              <div>
                <div class="email-mode-title">SMTP Direct (Rekomendasi)</div>
                <div class="email-mode-desc">Mengirim email langsung melalui server SMTP. Paling mudah dan fleksibel.</div>
                <div class="email-mode-features">
                  <div class="email-mode-feature pros">
                    <span>✓</span> Paling mudah setup (hanya butuh SMTP credentials)
                  </div>
                  <div class="email-mode-feature pros">
                    <span>✓</span> Sangat fleksibel (bisa pakai provider apa saja)
                  </div>
                  <div class="email-mode-feature pros">
                    <span>✓</span> Tidak perlu Outlook di server atau Azure AD
                  </div>
                </div>
              </div>
            </label>
            
            <!-- Client Engine Card -->
            <label class="email-mode-card client-engine <?= $currentEmailMode === 'client_engine' ? 'selected' : '' ?>">
              <input type="radio" name="email_sending_mode" value="client_engine" <?= $currentEmailMode === 'client_engine' ? 'checked' : '' ?> class="email-mode-radio">
              <div class="email-mode-icon">💻</div>
              <div>
                <div class="email-mode-title">Client Engine (Local Outlook)</div>
                <div class="email-mode-desc">Kirim email dari Outlook lokal komputer user menggunakan aplikasi Python. Ideal untuk distributed teams.</div>
                <div class="email-mode-features">
                  <div class="email-mode-feature pros">
                    <span>✓</span> Setiap user kirim dari Outlook lokal mereka
                  </div>
                  <div class="email-mode-feature pros">
                    <span>✓</span> Sent items di masing-masing komputer user
                  </div>
                  <div class="email-mode-feature pros">
                    <span>✓</span> Tidak perlu Outlook di server
                  </div>
                  <div class="email-mode-feature cons">
                    <span>⚠</span> Perlu install aplikasi Python di setiap client
                  </div>
                </div>
              </div>
            </label>
          </div>
          
          <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:12px;border-radius:8px;margin:20px 0;">
            <strong>⚠️ Perhatian:</strong> Setelah mengubah mode, sistem akan menggunakan metode baru untuk semua pengiriman email berikutnya.
          </div>
          
          <?php if ($currentEmailMode === 'graph_api'): ?>
          <div style="background:#dbeafe;border:1px solid #3b82f6;border-radius:8px;padding:16px;margin:20px 0;">
            <div style="font-weight:600;color:#1e40af;margin-bottom:12px;">ℹ️ Status Konfigurasi Graph API</div>
            <div style="display:grid;gap:8px;">
              <div style="display:flex;align-items:center;gap:8px;">
                <span>Tenant ID:</span>
                <?= empty(GRAPH_TENANT_ID) ? '<span style="background:#fee2e2;color:#dc2626;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">⚠️ Tidak dikonfigurasi</span>' : '<span style="background:#dcfce7;color:#059669;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">✓ Terkonfigurasi</span>' ?>
              </div>
              <div style="display:flex;align-items:center;gap:8px;">
                <span>Client ID:</span>
                <?= empty(GRAPH_CLIENT_ID) ? '<span style="background:#fee2e2;color:#dc2626;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">⚠️ Tidak dikonfigurasi</span>' : '<span style="background:#dcfce7;color:#059669;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">✓ Terkonfigurasi</span>' ?>
              </div>
              <div style="display:flex;align-items:center;gap:8px;">
                <span>Client Secret:</span>
                <?= empty(GRAPH_CLIENT_SECRET) ? '<span style="background:#fee2e2;color:#dc2626;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">⚠️ Tidak dikonfigurasi</span>' : '<span style="background:#dcfce7;color:#059669;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">✓ Terkonfigurasi</span>' ?>
              </div>
            </div>
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid #93c5fd;">
              <small style="color:#1e40af;">📖 Lihat file <code style="background:#eff6ff;padding:2px 6px;border-radius:4px;">GRAPH_API_SETUP.md</code> untuk panduan konfigurasi lengkap.</small>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if ($currentEmailMode === 'smtp'): ?>
          <div style="background:#dcfce7;border:1px solid #059669;border-radius:8px;padding:16px;margin:20px 0;">
            <div style="font-weight:600;color:#065f46;margin-bottom:12px;">ℹ️ Status Konfigurasi SMTP</div>
            <div style="display:grid;gap:8px;">
              <div style="display:flex;align-items:center;gap:8px;">
                <span>SMTP Host:</span>
                <?= empty(SMTP_HOST) ? '<span style="background:#fee2e2;color:#dc2626;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">⚠️ Tidak dikonfigurasi</span>' : '<span style="background:#dcfce7;color:#059669;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">✓ ' . e(SMTP_HOST) . '</span>' ?>
              </div>
              <div style="display:flex;align-items:center;gap:8px;">
                <span>SMTP Port:</span>
                <?= empty(SMTP_PORT) ? '<span style="background:#fee2e2;color:#dc2626;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">⚠️ Tidak dikonfigurasi</span>' : '<span style="background:#dcfce7;color:#059669;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">✓ ' . e(SMTP_PORT) . '</span>' ?>
              </div>
              <div style="display:flex;align-items:center;gap:8px;">
                <span>SMTP Username:</span>
                <?= empty(SMTP_USERNAME) ? '<span style="background:#fee2e2;color:#dc2626;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">⚠️ Tidak dikonfigurasi</span>' : '<span style="background:#dcfce7;color:#059669;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">✓ ' . e(SMTP_USERNAME) . '</span>' ?>
              </div>
            </div>
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid #86efac;">
              <small style="color:#065f46;">📖 Konfigurasi SMTP di bawah (SMTP Configuration Form).</small>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if ($currentEmailMode === 'client_engine'): ?>
          <div style="background:#f3e8ff;border:1px solid #8b5cf6;border-radius:8px;padding:16px;margin:20px 0;">
            <div style="font-weight:600;color:#5b21b6;margin-bottom:12px;">ℹ️ Status Client Engine</div>
            <div style="display:grid;gap:8px;">
              <?php
              $activeTokensCount = count(array_filter($userTokens, fn($t) => $t['is_active']));
              $pendingEmailsCount = 0;
              try {
                  $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_queue WHERE user_id = ? AND status = 'pending'");
                  $stmt->execute([$currentUser['id']]);
                  $pendingEmailsCount = $stmt->fetchColumn();
              } catch (Exception $e) {
                  // Table might not exist yet
              }
              ?>
              <div style="display:flex;align-items:center;gap:8px;">
                <span>Token Aktif:</span>
                <?= $activeTokensCount > 0 
                    ? '<span style="background:#dcfce7;color:#059669;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">✓ ' . $activeTokensCount . ' token aktif</span>' 
                    : '<span style="background:#fee2e2;color:#dc2626;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;">⚠️ Tidak ada token aktif</span>' 
                ?>
              </div>
              <div style="display:flex;align-items:center;gap:8px;">
                <span>Email Menunggu:</span>
                <span style="background:#dbeafe;color:#1e40af;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;"><?= $pendingEmailsCount ?> email</span>
              </div>
            </div>
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid #c4b5fd;">
              <small style="color:#5b21b6;">📖 Setup Client Engine di bawah (Token Management & Download).</small>
            </div>
          </div>
          <?php endif; ?>
          
          <button type="submit" class="btn" style="background:#3b82f6;padding:12px 24px;border-radius:8px;font-weight:600;font-size:14px;box-shadow:0 4px 12px rgba(59, 130, 246, 0.3);transition:all 0.2s ease;">
            Simpan Konfigurasi
          </button>
        </form>
        
        <!-- SMTP Configuration Form -->
        <div style="margin-top:40px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:12px;padding:24px;">
          <h5 style="margin-top:0;margin-bottom:16px;">📧 SMTP Configuration Form</h5>
          <p style="color:#666;font-size:14px;margin-bottom:20px;">Konfigurasi SMTP server untuk mengirim email.</p>
          
          <form method="post" action="?tab=email">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="save_smtp_config">
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div>
                <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">SMTP Host</label>
                <input type="text" name="smtp_host" value="<?= e(SMTP_HOST) ?>" placeholder="smtp.gmail.com" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box;">
                <small style="color:#666;font-size:12px;">Contoh: smtp.gmail.com, smtp.office365.com</small>
              </div>
              
              <div>
                <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">SMTP Port</label>
                <input type="number" name="smtp_port" value="<?= e(SMTP_PORT) ?>" placeholder="587" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box;">
                <small style="color:#666;font-size:12px;">Biasanya 587 (TLS) atau 465 (SSL)</small>
              </div>
              
              <div>
                <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">SMTP Username</label>
                <input type="text" name="smtp_username" value="<?= e(SMTP_USERNAME) ?>" placeholder="your-email@gmail.com" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box;">
                <small style="color:#666;font-size:12px;">Email SMTP Anda</small>
              </div>
              
              <div>
                <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">SMTP Password</label>
                <input type="password" name="smtp_password" value="<?= e(SMTP_PASSWORD) ?>" placeholder="••••••••" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box;">
                <small style="color:#666;font-size:12px;">Password atau App Password</small>
              </div>
              
              <div>
                <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">Encryption</label>
                <select name="smtp_encryption" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box;">
                  <option value="tls" <?= SMTP_ENCRYPTION === 'tls' ? 'selected' : '' ?>>TLS (Recommended)</option>
                  <option value="ssl" <?= SMTP_ENCRYPTION === 'ssl' ? 'selected' : '' ?>>SSL</option>
                  <option value="none" <?= SMTP_ENCRYPTION === 'none' ? 'selected' : '' ?>>None (Not Recommended)</option>
                </select>
                <small style="color:#666;font-size:12px;">TLS/SSL untuk keamanan enkripsi</small>
              </div>
              
              <div>
                <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">From Email</label>
                <input type="email" name="smtp_from_email" value="<?= e(SMTP_FROM_EMAIL) ?>" placeholder="sender@company.com" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box;">
                <small style="color:#666;font-size:12px;">Email pengirim (default: SMTP username)</small>
              </div>
              
              <div style="grid-column:1/-1;">
                <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">From Name</label>
                <input type="text" name="smtp_from_name" value="<?= e(SMTP_FROM_NAME) ?>" placeholder="Email Dispatcher" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box;">
                <small style="color:#666;font-size:12px;">Nama pengirim yang akan tampil di email</small>
              </div>
            </div>
            
            <button type="submit" class="btn" style="background:#059669;padding:12px 24px;border-radius:8px;font-weight:600;font-size:14px;box-shadow:0 4px 12px rgba(5, 150, 105, 0.3);transition:all 0.2s ease;margin-top:20px;">
              Simpan Konfigurasi SMTP
            </button>
          </form>
        </div>
        
        <!-- Client Engine Section -->
        <div style="margin-top:40px;background:#f3e8ff;border:1px solid #8b5cf6;border-radius:12px;padding:24px;">
          <h5 style="margin-top:0;margin-bottom:16px;">💻 Client Engine (Local Outlook)</h5>
          <p style="color:#666;font-size:14px;margin-bottom:20px;">
            Client Engine memungkinkan pengiriman email dari Outlook lokal di komputer Anda. 
            Download aplikasi Python dan generate token untuk menghubungkan ke server.
          </p>
          
          <!-- Token Management -->
          <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:20px;">
            <h6 style="margin-top:0;margin-bottom:12px;">🔑 Token Management</h6>
            
            <?php if (!empty($userTokens)): ?>
            <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">
              <thead>
                <tr style="border-bottom:2px solid #e5e7eb;">
                  <th style="text-align:left;padding:8px;">Nama</th>
                  <th style="text-align:left;padding:8px;">Deskripsi</th>
                  <th style="text-align:center;padding:8px;">Status</th>
                  <th style="text-align:left;padding:8px;">Terakhir Digunakan</th>
                  <th style="text-align:center;padding:8px;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($userTokens as $token): ?>
                <tr style="border-bottom:1px solid #e5e7eb;">
                  <td style="padding:8px;"><?= e($token['token_name']) ?></td>
                  <td style="padding:8px;"><?= e($token['description'] ?? '-') ?></td>
                  <td style="padding:8px;text-align:center;">
                    <?php if ($token['is_active']): ?>
                      <span style="background:#dcfce7;color:#059669;padding:2px 8px;border-radius:4px;font-size:12px;">Aktif</span>
                    <?php else: ?>
                      <span style="background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:4px;font-size:12px;">Nonaktif</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:8px;">
                    <?= $token['last_used_at'] ? date('d/m/Y H:i', strtotime($token['last_used_at'])) : 'Belum pernah' ?>
                  </td>
                  <td style="padding:8px;text-align:center;">
                    <?php if ($token['is_active']): ?>
                    <form method="post" action="?tab=email" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                      <input type="hidden" name="action" value="revoke_engine_token">
                      <input type="hidden" name="token_id" value="<?= $token['id'] ?>">
                      <button type="submit" class="btn" style="background:#dc2626;padding:4px 12px;font-size:12px;" onclick="return confirm('Yakin ingin menonaktifkan token ini?')">
                        Nonaktifkan
                      </button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php else: ?>
            <p style="color:#666;font-size:13px;margin-bottom:16px;">
              Belum ada token. Generate token baru untuk menggunakan Client Engine.
            </p>
            <?php endif; ?>
            
            <!-- Generate New Token Form -->
            <form method="post" action="?tab=email">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="generate_engine_token">
              
              <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;">
                <div>
                  <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">Nama Token</label>
                  <input type="text" name="token_name" placeholder="Contoh: Laptop Kantor" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box;">
                </div>
                <div>
                  <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">Deskripsi (Opsional)</label>
                  <input type="text" name="token_description" placeholder="Contoh: Komputer di kantor utama" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box;">
                </div>
                <div>
                  <button type="submit" class="btn" style="background:#8b5cf6;padding:10px 20px;border-radius:6px;font-weight:600;font-size:14px;">
                    + Generate Token
                  </button>
                </div>
              </div>
            </form>
            
            <?php if (strpos($message, 'Token berhasil dibuat') !== false): ?>
            <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:12px;border-radius:4px;margin-top:16px;">
              <strong>⚠️ Penting:</strong> Copy token di atas dan simpan dengan aman. Token hanya ditampilkan sekali!
            </div>
            <?php endif; ?>
          </div>
          
          <!-- Download Section -->
          <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;">
            <h6 style="margin-top:0;margin-bottom:12px;">⬇️ Download Client Engine</h6>
            
            <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:12px;border-radius:4px;margin-bottom:16px;">
              <strong>💡 Rekomendasi:</strong> Gunakan <strong>.exe version</strong> jika komputer Anda tidak memiliki Python terinstall.
            </div>
            
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:16px;">
              <!-- .exe Download (Recommended) -->
              <div style="background:linear-gradient(135deg, #dcfce7 0%, #ecfdf5 100%);border:2px solid #22c55e;border-radius:6px;padding:12px;position:relative;">
                <div style="position:absolute;top:-10px;left:12px;background:#22c55e;color:#fff;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600;">⭐ REKOMENDASI</div>
                <div style="font-weight:600;margin-bottom:8px;margin-top:10px;">⚙️ Windows Executable (.exe)</div>
                <p style="font-size:13px;color:#666;margin-bottom:12px;">
                  <strong>Tidak perlu Python!</strong> File executable standalone untuk Windows. Tinggal download, buat config, jalankan.
                </p>
                <?php 
                $exeExists = file_exists(__DIR__ . '/../client_engine/dist/EmailSenderEngine.exe');
                if ($exeExists): 
                ?>
                <a href="../client_engine/dist/EmailSenderEngine.exe" download class="btn" style="background:#22c55e;padding:8px 16px;font-size:13px;display:inline-block;text-decoration:none;color:#fff;">
                  ⬇️ Download EmailSenderEngine.exe
                </a>
                <?php else: ?>
                <div style="background:#fee2e2;border:1px solid #ef4444;border-radius:4px;padding:8px;font-size:12px;color:#dc2626;">
                  ⚠️ File .exe belum tersedia. Hubungi administrator untuk build.
                </div>
                <?php endif; ?>
              </div>
              
              <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;">
                <div style="font-weight:600;margin-bottom:8px;">📄 Python Script</div>
                <p style="font-size:13px;color:#666;margin-bottom:12px;">File utama aplikasi Python. Perlu install Python 3.7+ dan dependencies.</p>
                <a href="../client_engine/email_sender_engine.py" download class="btn" style="background:#3b82f6;padding:8px 16px;font-size:13px;display:inline-block;text-decoration:none;color:#fff;">
                  Download .py
                </a>
              </div>
              
              <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;">
                <div style="font-weight:600;margin-bottom:8px;">🚀 Windows Launcher</div>
                <p style="font-size:13px;color:#666;margin-bottom:12px;">Batch file untuk menjalankan engine dengan mudah (untuk Python version).</p>
                <a href="../client_engine/run_engine.bat" download class="btn" style="background:#3b82f6;padding:8px 16px;font-size:13px;display:inline-block;text-decoration:none;color:#fff;">
                  Download .bat
                </a>
              </div>
              
              <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;">
                <div style="font-weight:600;margin-bottom:8px;">📖 Dokumentasi</div>
                <p style="font-size:13px;color:#666;margin-bottom:12px;">Panduan lengkap instalasi dan penggunaan Client Engine.</p>
                <a href="../client_engine/README.md" target="_blank" class="btn" style="background:#6b7280;padding:8px 16px;font-size:13px;display:inline-block;text-decoration:none;color:#fff;">
                  Baca README
                </a>
              </div>
            </div>
            
            <div style="margin-top:16px;padding:12px;background:#eff6ff;border-left:4px solid #3b82f6;border-radius:4px;">
              <strong>Quick Start (.exe Version - Rekomendasi):</strong>
              <ol style="margin:8px 0;padding-left:20px;font-size:13px;color:#374151;">
                <li>Download <strong>EmailSenderEngine.exe</strong> di atas</li>
                <li>Buat file <code>.env</code> di folder yang sama dengan isi:
                  <pre style="background:#f3f4f6;padding:8px;border-radius:4px;margin-top:4px;font-size:12px;">EMAIL_ENGINE_SERVER=<?= e((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?>
EMAIL_ENGINE_TOKEN=your-token-here
EMAIL_ENGINE_DELAY=1000</pre>
                </li>
                <li>Double-click <code>EmailSenderEngine.exe</code> atau jalankan dari Command Prompt:
                  <code>EmailSenderEngine.exe --config .env --daemon</code>
                </li>
              </ol>
            </div>
            
            <div style="margin-top:12px;padding:12px;background:#f9fafb;border-left:4px solid #6b7280;border-radius:4px;">
              <strong>Quick Start (Python Version):</strong>
              <ol style="margin:8px 0;padding-left:20px;font-size:13px;color:#374151;">
                <li>Download <code>email_sender_engine.py</code> dan <code>run_engine.bat</code></li>
                <li>Install Python dan dependencies: <code>pip install requests pywin32</code></li>
                <li>Generate token di atas</li>
                <li>Jalankan: <code>python email_sender_engine.py --config .env --daemon</code></li>
              </ol>
            </div>
          </div>
        </div>
        
        <div style="margin-top:24px;">
          <a href="index.php" class="btn secondary">⟵ Kembali</a>
        </div>
      </div>

      <!-- Tab 4: Security & Deliverability (Admin Only) -->
      <div id="security" class="tab-content <?= $activeTab === 'security' ? 'active' : '' ?>">
        <h4>🛡️ Keamanan & Deliverability</h4>
        <p style="color:#666;margin-bottom:16px;">Kelola keamanan email, konfigurasi DKIM/SPF/DMARC, suppression list, webhook, dan analytics.</p>

        <!-- Deliverability Config -->
        <div style="background:#f0f5ff;border:1px solid #3b82f6;border-radius:8px;padding:16px;margin-bottom:16px;">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <span style="font-size:24px;">🔐</span>
            <div>
              <h5 style="margin:0;">DKIM / SPF / DMARC Config</h5>
              <p style="margin:4px 0 0 0;color:#666;font-size:13px;">Konfigurasi autentikasi email untuk meningkatkan deliverability</p>
            </div>
          </div>
          <a href="deliverability.php" class="btn" style="background:#2563eb;">Buka Konfigurasi →</a>
        </div>

        <!-- Suppression List -->
        <div style="background:#fef2f2;border:1px solid #ef4444;border-radius:8px;padding:16px;margin-bottom:16px;">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <span style="font-size:24px;">🚫</span>
            <div>
              <h5 style="margin:0;">Suppression List</h5>
              <p style="margin:4px 0 0 0;color:#666;font-size:13px;">Kelola email yang diblokir (bounce, unsubscribe, spam complaint)</p>
            </div>
          </div>
          <a href="suppression.php" class="btn" style="background:#dc2626;">Kelola Suppression →</a>
        </div>

        <!-- Webhook Management -->
        <div style="background:#f0fdf4;border:1px solid #22c55e;border-radius:8px;padding:16px;margin-bottom:16px;">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <span style="font-size:24px;">🔗</span>
            <div>
              <h5 style="margin:0;">Webhook Management</h5>
              <p style="margin:4px 0 0 0;color:#666;font-size:13px;">Konfigurasi endpoint webhook untuk event notifications</p>
            </div>
          </div>
          <a href="webhooks.php" class="btn" style="background:#16a34a;">Kelola Webhook →</a>
        </div>

        <!-- Analytics Dashboard -->
        <div style="background:#faf5ff;border:1px solid #a855f7;border-radius:8px;padding:16px;margin-bottom:16px;">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <span style="font-size:24px;">📊</span>
            <div>
              <h5 style="margin:0;">Analytics Dashboard</h5>
              <p style="margin:4px 0 0 0;color:#666;font-size:13px;">Lihat statistik email opens, clicks, bounces, dan performa campaign</p>
            </div>
          </div>
          <a href="analytics.php" class="btn" style="background:#9333ea;">Lihat Analytics →</a>
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

async function confirmDeleteUser(userId, username) {
  const result = await Swal.fire({
    title: 'Hapus User?',
    html: '<p style="color: #666; font-size: 14px;">Anda akan menghapus user <strong>' + username + '</strong>.</p><p style="color: #dc3545; font-weight: bold;">⚠️ Tindakan ini TIDAK DAPAT DIBATALKAN!</p>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Batal',
    cancelButtonColor: '#6b7280',
    allowOutsideClick: false,
    allowEscapeKey: false
  });
  
  if (result.isConfirmed) {
    // Prompt for admin password
    const { value: password } = await Swal.fire({
      title: 'Konfirmasi Password Administrator',
      input: 'password',
      inputLabel: 'Masukkan password Anda untuk melanjutkan:',
      inputPlaceholder: 'Password',
      inputAttributes: {
        maxlength: 50,
        autocapitalize: 'off',
        autocorrect: 'off'
      },
      showCancelButton: true,
      confirmButtonText: 'Konfirmasi',
      confirmButtonColor: '#dc2626',
      cancelButtonText: 'Batal',
      cancelButtonColor: '#6b7280',
      allowOutsideClick: false,
      allowEscapeKey: false,
      inputValidator: (value) => {
        if (!value) {
          return 'Password wajib diisi!'
        }
      }
    });
    
    if (password) {
      const form = document.createElement('form');
      form.method = 'post';
      form.action = '?tab=users';
      
      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = 'csrf_token';
      csrfInput.value = '<?= e($csrf) ?>';
      
      const actionInput = document.createElement('input');
      actionInput.type = 'hidden';
      actionInput.name = 'action';
      actionInput.value = 'delete';
      
      const deleteIdInput = document.createElement('input');
      deleteIdInput.type = 'hidden';
      deleteIdInput.name = 'delete_id';
      deleteIdInput.value = userId;
      
      const passwordInput = document.createElement('input');
      passwordInput.type = 'hidden';
      passwordInput.name = 'admin_password';
      passwordInput.value = password;
      
      form.appendChild(csrfInput);
      form.appendChild(actionInput);
      form.appendChild(deleteIdInput);
      form.appendChild(passwordInput);
      document.body.appendChild(form);
      form.submit();
    }
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
</body>
</html>

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
