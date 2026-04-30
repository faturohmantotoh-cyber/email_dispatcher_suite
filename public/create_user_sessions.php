<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';

$pdo = DB::conn();

echo "<h2>🔧 Creating user_sessions table...</h2>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;}</style>";

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS user_sessions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      session_id VARCHAR(255) NOT NULL,
      ip_address VARCHAR(45) NOT NULL,
      hostname VARCHAR(255) DEFAULT NULL,
      user_agent TEXT,
      login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
      last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY unique_session (session_id),
      INDEX idx_user_id (user_id),
      INDEX idx_last_activity (last_activity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $pdo->exec($sql);
    echo "<p class='success'>✅ Table user_sessions created successfully!</p>";
    
    // Verify
    $check = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
    if ($check->rowCount() > 0) {
        echo "<p class='success'>✅ Verification: Table exists.</p>";
    } else {
        echo "<p class='error'>❌ Verification failed.</p>";
    }
    
    echo "<p><a href='debug_sessions.php'>← Check Sessions</a> | <a href='index.php'>← Back to Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}
