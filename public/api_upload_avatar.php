<?php
// public/api_upload_avatar.php - API untuk upload custom avatar
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');

$user = $_SESSION['user'] ?? null;
if (!$user) {
  http_response_code(401);
  exit(json_encode(['error' => 'Unauthorized']));
}

try {
  // Ensure avatar column exists
  $pdo = DB::conn();
  try {
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
    $hasAvatarColumn = false;
    foreach ($columns as $col) {
      if ($col['Field'] === 'avatar') {
        $hasAvatarColumn = true;
        break;
      }
    }
    
    if (!$hasAvatarColumn) {
      $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL AFTER password_hash");
    }
  } catch (Exception $e) {
    try {
      $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL");
    } catch (Exception $e2) {
      // Kolom sudah ada, lanjutkan
    }
  }

  // Validasi file upload
  if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(['error' => 'Tidak ada file atau upload gagal']));
  }

  $file = $_FILES['avatar'];
  $maxSize = 5 * 1024 * 1024; // 5MB
  $allowedMimes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
  $allowedExts = ['jpg', 'jpeg', 'png', 'svg', 'webp'];

  // Cek ukuran file
  if ($file['size'] > $maxSize) {
    http_response_code(400);
    exit(json_encode(['error' => 'File terlalu besar. Maksimal 5MB']));
  }

  // Cek MIME type
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);

  if (!in_array($mimeType, $allowedMimes)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Format file tidak didukung. Gunakan JPG, PNG, SVG, atau WebP']));
  }

  // Cek extension
  $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
  if (!in_array(strtolower($ext), $allowedExts)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Extension tidak didukung']));
  }

  // Buat folder jika belum ada
  $uploadDir = __DIR__ . '/../storage/avatars';
  if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
      http_response_code(500);
      exit(json_encode(['error' => 'Gagal membuat direktori upload']));
    }
  }

  // Generate nama file unique
  $userId = $user['id'];
  $filename = 'avatar_' . $userId . '_' . time() . '.' . strtolower($ext);
  $filepath = $uploadDir . '/' . $filename;

  // Move uploaded file
  if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    exit(json_encode(['error' => 'Gagal menyimpan file di server']));
  }

  // Hapus avatar lama jika ada (from storage/avatars/)
  try {
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldAvatar = $stmt->fetchColumn();

    if ($oldAvatar && strpos($oldAvatar, 'storage/avatars/') === 0) {
      $oldPath = __DIR__ . '/../' . $oldAvatar;
      if (file_exists($oldPath) && $oldAvatar !== $filename) {
        @unlink($oldPath);
      }
    }
  } catch (Exception $e) {
    // Tidak apa jika gagal hapus file lama
    error_log("Failed to delete old avatar: " . $e->getMessage());
  }

  // Return path relative ke public folder
  $relativePath = 'storage/avatars/' . $filename;

  echo json_encode([
    'success' => true,
    'avatar' => $relativePath,
    'filename' => $filename,
    'message' => 'File berhasil diupload'
  ]);

} catch (Exception $e) {
  error_log("Upload avatar error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Server error: ' . $e->getMessage()
  ]);
}
?>
