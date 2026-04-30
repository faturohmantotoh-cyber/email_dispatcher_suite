<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';

$pdo = DB::conn();

echo "<h2>Current User Sessions</h2>";
echo "<pre>";

// Check if table exists
$tableCheck = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
if ($tableCheck->rowCount() == 0) {
    echo "Table user_sessions does NOT exist!\n";
} else {
    echo "Table user_sessions exists.\n\n";
    
    // Get all sessions
    $result = $pdo->query("SELECT * FROM user_sessions");
    $sessions = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total sessions: " . count($sessions) . "\n\n";
    
    foreach ($sessions as $session) {
        echo "ID: " . $session['id'] . "\n";
        echo "User ID: " . $session['user_id'] . "\n";
        echo "Session ID: " . $session['session_id'] . "\n";
        echo "IP Address: " . $session['ip_address'] . "\n";
        echo "Hostname: " . $session['hostname'] . "\n";
        echo "Login Time: " . $session['login_time'] . "\n";
        echo "Last Activity: " . $session['last_activity'] . "\n";
        echo "----------------\n";
    }
}

echo "\n\n<h2>Users Table</h2>";
$users = $pdo->query("SELECT id, username, display_name, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo "ID: {$u['id']} | Username: {$u['username']} | Display: {$u['display_name']} | Role: {$u['role']}\n";
}

echo "\n\n<h2>Active Users Query Result</h2>";
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
echo "Active users count: " . count($active_users) . "\n\n";
foreach ($active_users as $au) {
    echo "User: {$au['display_name']} ({$au['username']}) | Role: {$au['role']} | IP: {$au['ip_address']} | Idle: {$au['idle_seconds']}s\n";
}

echo "</pre>";
