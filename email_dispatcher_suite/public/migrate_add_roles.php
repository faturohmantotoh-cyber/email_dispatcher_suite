<?php
// public/migrate_add_roles.php
// This script adds role support to the users table
// Run this once: http://localhost/email_dispatcher_suite/public/migrate_add_roles.php

session_start();
require_once __DIR__ . '/../config_db.php';
require_once __DIR__ . '/../lib/db.php';

try {
    $pdo = DB::conn();
    
    echo "Checking users table structure...\n";
    
    // Check if role column exists
    $result = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($result->rowCount() === 0) {
        echo "Adding role column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'user' AFTER password_hash");
        echo "✅ Role column added (default: 'user')\n";
    } else {
        echo "✅ Role column already exists\n";
    }
    
    // Set admin user role
    $pdo->exec("UPDATE users SET role = 'admin' WHERE username = 'admin@local'");
    
    echo "\n✅ Migration complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
