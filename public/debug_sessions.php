<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';

$pdo = DB::conn();

echo "<h2>🔍 Debug: User Sessions</h2>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;} table{border-collapse:collapse;width:100%;margin:20px 0;} th,td{border:1px solid #ddd;padding:10px;text-align:left;} th{background:#4CAF50;color:white;} .error{color:red;font-weight:bold;} .success{color:green;font-weight:bold;}</style>";

// Check if table exists
$tableCheck = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
if ($tableCheck->rowCount() == 0) {
    echo "<p class='error'>❌ Table user_sessions does NOT exist!</p>";
} else {
    echo "<p class='success'>✅ Table user_sessions exists.</p>";
    
    // Get all sessions
    $result = $pdo->query("SELECT * FROM user_sessions");
    $sessions = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>All Sessions in Database</h3>";
    echo "<table>";
    echo "<tr><th>ID</th><th>User ID</th><th>Session ID</th><th>IP Address</th><th>Hostname</th><th>Login Time</th><th>Last Activity</th></tr>";
    foreach ($sessions as $session) {
        echo "<tr>";
        echo "<td>{$session['id']}</td>";
        echo "<td>{$session['user_id']}</td>";
        echo "<td>" . substr($session['session_id'], 0, 20) . "...</td>";
        echo "<td>{$session['ip_address']}</td>";
        echo "<td>{$session['hostname']}</td>";
        echo "<td>{$session['login_time']}</td>";
        echo "<td>{$session['last_activity']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>Users Table</h3>";
$users = $pdo->query("SELECT id, username, display_name, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
echo "<table>";
echo "<tr><th>ID</th><th>Username</th><th>Display Name</th><th>Role</th></tr>";
foreach ($users as $u) {
    $highlight = ($u['username'] === 'samsul.falakh') ? 'style="background:#ffff00;"' : '';
    echo "<tr $highlight>";
    echo "<td>{$u['id']}</td>";
    echo "<td>{$u['username']}</td>";
    echo "<td>{$u['display_name']}</td>";
    echo "<td>{$u['role']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Active Users Query Result (Last 5 min)</h3>";
$active_users_stmt = $pdo->query("
    SELECT s.user_id, u.username, u.display_name, u.role,
           s.ip_address, s.hostname, s.last_activity,
           TIMESTAMPDIFF(SECOND, s.last_activity, NOW()) as idle_seconds
    FROM user_sessions s
    JOIN users u ON s.user_id = u.id
    WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY s.last_activity DESC
");
$active_users = $active_users_stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Active users count: <strong>" . count($active_users) . "</strong></p>";
echo "<table>";
echo "<tr><th>User</th><th>Username</th><th>Role</th><th>IP</th><th>Hostname</th><th>Idle (s)</th></tr>";
foreach ($active_users as $au) {
    $highlight = ($au['username'] === 'samsul.falakh') ? 'style="background:#ffff00;"' : '';
    echo "<tr $highlight>";
    echo "<td>{$au['display_name']}</td>";
    echo "<td>{$au['username']}</td>";
    echo "<td>{$au['role']}</td>";
    echo "<td>{$au['ip_address']}</td>";
    echo "<td>{$au['hostname']}</td>";
    echo "<td>{$au['idle_seconds']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Current Session Info</h3>";
echo "<ul>";
echo "<li>Current User ID: " . ($user['id'] ?? 'NOT SET') . "</li>";
echo "<li>Current Username: " . ($user['username'] ?? 'NOT SET') . "</li>";
echo "<li>Session ID: " . session_id() . "</li>";
echo "<li>Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "</li>";
echo "</ul>";

echo "<p><a href='index.php'>← Back to Dashboard</a></p>";
