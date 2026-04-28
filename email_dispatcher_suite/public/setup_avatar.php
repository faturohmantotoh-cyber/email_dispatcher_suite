<?php
// public/setup_avatar.php - Setup script untuk menambahkan avatar column ke users table
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: text/plain');

try {
  $pdo = DB::conn();
  
  echo "=== Avatar Setup ===\n\n";
  
  // Check if avatar column exists
  $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
  $hasAvatarColumn = false;
  foreach ($columns as $col) {
    if ($col['Field'] === 'avatar') {
      $hasAvatarColumn = true;
      break;
    }
  }
  
  if ($hasAvatarColumn) {
    echo "✓ Column 'avatar' already exists in 'users' table\n";
  } else {
    echo "✗ Column 'avatar' not found, adding...\n";
    try {
      $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL AFTER password_hash");
      echo "✓ Column 'avatar' added successfully\n";
    } catch (Exception $e) {
      // Try alternate syntax without AFTER
      $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL");
      echo "✓ Column 'avatar' added successfully (without position)\n";
    }
  }
  
  // Create avatars storage directory
  $avatarsDir = __DIR__ . '/../storage/avatars';
  if (!is_dir($avatarsDir)) {
    if (mkdir($avatarsDir, 0755, true)) {
      echo "✓ Created directory: storage/avatars\n";
    } else {
      echo "✗ Failed to create directory: storage/avatars\n";
    }
  } else {
    echo "✓ Directory 'storage/avatars' already exists\n";
  }
  
  // Check preset avatars
  $avatarsPreset = __DIR__ . '/../assets/img/avatars';
  $presetFiles = ['avatar1.svg', 'avatar2.svg', 'avatar3.svg', 'avatar4.svg', 'avatar5.svg', 'avatar6.svg'];
  echo "\n=== Preset Avatars ===\n";
  
  $allExist = true;
  foreach ($presetFiles as $file) {
    $path = $avatarsPreset . '/' . $file;
    if (file_exists($path)) {
      echo "✓ $file\n";
    } else {
      echo "✗ $file (missing)\n";
      $allExist = false;
    }
  }
  
  echo "\n=== Setup Complete ===\n";
  echo $allExist ? "✓ All preset avatars are ready\n" : "✗ Some preset avatars are missing\n";
  echo "✓ Database ready for avatar uploads\n";
  echo "\nYou can now use the avatar editor in index.php!\n";
  
} catch (Exception $e) {
  http_response_code(500);
  echo "ERROR: " . $e->getMessage() . "\n";
}
?>
