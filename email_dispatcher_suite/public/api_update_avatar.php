<?php
// public/api_update_avatar.php - API untuk mengubah user avatar
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');

$user = $_SESSION['user'] ?? null;
if (!$user) {
  http_response_code(401);
  exit(json_encode(['error' => 'Unauthorized']));
}

try {
  $pdo = DB::conn();
  
  // Cek dan tambahkan kolom avatar jika belum ada
  try {
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
    $hasAvatarColumn = false;
    foreach ($columns as $col) {
      if ($col['Field'] === 'avatar') {
        $hasAvatarColumn = true;
        break;
      }
    }
    
    // Jika belum ada, tambahkan kolom
    if (!$hasAvatarColumn) {
      $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL AFTER password_hash");
    }
  } catch (Exception $e) {
    // If DESCRIBE fails, try to add column anyway (might already exist)
    try {
      $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL");
    } catch (Exception $e2) {
      // Kolom sudah ada atau error lain, lanjutkan
    }
  }
  
  $method = $_SERVER['REQUEST_METHOD'];
  
  if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $avatar = $data['avatar'] ?? null;
    
    if (!$avatar) {
      http_response_code(400);
      exit(json_encode(['error' => 'Avatar tidak valid']));
    }
    
    // Validasi format avatar (preset atau custom)
    $isPreset = preg_match('/^avatar[1-6]\.svg$/', $avatar);
    $isCustom = strpos($avatar, 'storage/avatars/') === 0;
    
    if (!$isPreset && !$isCustom) {
      http_response_code(400);
      exit(json_encode(['error' => 'Format avatar tidak valid']));
    }
    
    // Validasi file custom ada
    if ($isCustom) {
      $customPath = __DIR__ . '/../' . $avatar;
      if (!file_exists($customPath)) {
        http_response_code(400);
        exit(json_encode(['error' => 'File avatar tidak ditemukan']));
      }
    }
    
    // Update database
    $stmt = $pdo->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$avatar, $user['id']]);
    
    // Update session
    $_SESSION['user']['avatar'] = $avatar;
    
    echo json_encode([
      'success' => true,
      'avatar' => $avatar,
      'message' => 'Avatar berhasil diubah'
    ]);
    exit;
  }
  
  if ($method === 'GET') {
    // Get user avatar
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
      'success' => true,
      'avatar' => $result['avatar'] ?? null
    ]);
    exit;
  }
  
} catch (Exception $e) {
  error_log("Avatar error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Server error: ' . $e->getMessage()
  ]);
}
?>
