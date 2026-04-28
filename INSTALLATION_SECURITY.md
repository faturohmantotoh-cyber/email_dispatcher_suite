# 🔒 Panduan Instalasi Security Enhancements

## ⚙️ Langkah-Langkah Implementasi

### STEP 1: Backup Database
```bash
# Backup database sebelum migration
mysqldump -u root email_dispatcher > backup_email_dispatcher_$(date +%Y%m%d).sql
```

### STEP 2: Jalankan SQL Migration
```bash
# Connect ke MySQL dan jalankan migration
mysql -u root email_dispatcher < db/migration_security_enhancements.sql

# Atau via MySQL client:
# mysql> source /path/to/db/migration_security_enhancements.sql;
```

**MySQL Commands untuk manual execution:**
```sql
-- Copy dan paste di MySQL client
USE email_dispatcher;

-- Check if tables created
SHOW TABLES LIKE 'security%';
SHOW TABLES LIKE 'password%';
SHOW TABLES LIKE 'audit%';

-- Check new columns on users table
DESCRIBE users;
```

### STEP 3: Verify File Permissions
```bash
# Folder storage harus writable
chmod 755 storage/
chmod 755 storage/logs/
chmod 755 storage/temp/

# Log files permissions
chmod 644 storage/logs/*.log
```

### STEP 4: Test Security Features

#### Test 1: Rate Limiting pada Login
```
1. Buka http://localhost/email_dispatcher_suite/public/login.php
2. Masukkan username yang benar
3. Masukkan password SALAH 6 kali berturut-turut
4. Pada attempt ke-5 akan error "Terlalu banyak percobaan"
5. Account akan terkunci 15 menit
```

#### Test 2: Password Strength Validation
```
1. Login dengan admin account
2. Pergi ke Settings > Password
3. Coba ubah password dengan:
   a. Password < 12 karakter → REJECT ❌
   b. Password tanpa uppercase → REJECT ❌
   c. Password tanpa number → REJECT ❌
   d. Password tanpa special char (!@#$%^&*) → REJECT ❌
   e. Password kuat: "MyPassword123!" → ACCEPT ✅
```

#### Test 3: Create New User dengan Password Weak
```
1. Login sebagai Admin
2. Pergi ke Settings > Users
3. Tambah user baru
4. Input password "weak" → REJECT ❌
5. Input password "StrongPass123!" → ACCEPT ✅
6. User baru harus ubah password saat login pertama
```

#### Test 4: Password History
```
1. Login sebagai user yang baru dibuat
2. Masukkan password temporary → ACCEPTED
3. Pergi ke Settings > Password
4. Ubah password ke "NewPassword123!"
5. Logout dan login again
6. Coba ubah password ke "NewPassword123!" (password lama) → REJECT ❌
```

#### Test 5: CSRF Protection
```
1. Buka browser DevTools (F12)
2. Buka Console tab
3. Jalankan command:
   ```javascript
   // Get any form token
   const form = document.querySelector('form');
   form.querySelector('input[name="csrf_token"]').value;
   // Akan menampilkan token
   ```
4. Coba modifikasi form POST tanpa token → REJECTED ❌
```

#### Test 6: HTTPS/Security Headers
```bash
# Check security headers (gunakan curl)
curl -I https://localhost/email_dispatcher_suite/public/login.php

# Output harus menampilkan:
# X-Frame-Options: SAMEORIGIN
# X-Content-Type-Options: nosniff
# X-XSS-Protection: 1; mode=block
# Strict-Transport-Security: max-age=31536000
# Content-Security-Policy
```

### STEP 5: Configure Log Monitoring

#### Setup automatic log cleanup
```bash
# Add to crontab to cleanup old logs
# Edit crontab: crontab -e
# Add line untuk cleanup security logs (daily at 2 AM)
0 2 * * * mysql -u root email_dispatcher -e "CALL cleanup_old_logs();"
```

#### View Security Logs
```php
// In any admin dashboard page
<?php
$stmt = $pdo->query("
    SELECT event_type, action_key, details, ip_address, timestamp 
    FROM security_logs 
    ORDER BY timestamp DESC 
    LIMIT 100
");
$logs = $stmt->fetchAll();

foreach ($logs as $log) {
    echo date('Y-m-d H:i:s', strtotime($log['timestamp'])) . " | ";
    echo $log['event_type'] . " | ";
    echo $log['action_key'] . " | ";
    echo $log['ip_address'] . "\n";
}
?>
```

### STEP 6: Update .htaccess (Apache)
```apache
# .htaccess - Add security rules
# Create file: .htaccess di root directory

<IfModule mod_headers.c>
    # Security Headers
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-Content-Type-Options "nosniff"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    Header set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    
    # CORS untuk API (jika diperlukan)
    # Header set Access-Control-Allow-Origin "https://trusted-domain.com"
    # Header set Access-Control-Allow-Methods "GET, POST, OPTIONS"
    # Header set Access-Control-Allow-Credentials "true"
</IfModule>

# Disable directory listing
Options -Indexes

# Block direct PHP execution di storage folder
<FilesMatch "\.php$">
    Deny from all
</FilesMatch>

# Encrypt sensitive files
<FilesMatch "config_db\.php|security\.php|db\.php">
    Deny from all
</FilesMatch>

# Prevent access to sensitive directories
<FilesMatch "^\.">
    Deny from all
</FilesMatch>
```

### STEP 7: Nginx Configuration (jika menggunakan Nginx)
```nginx
# Add to nginx server block
server {
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    
    # HSTS untuk HTTPS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    
    # CSP
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net" always;
    
    # Block access to config files
    location ~ /config_db\.php {
        deny all;
    }
    
    location ~ /lib/ {
        deny all;
    }
    
    location ~ /db/ {
        deny all;
    }
    
    # Enable GZIP compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1000;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss;
}
```

### STEP 8: PHP Configuration (php.ini)
```ini
; Recommended security settings
[PHP]

; Disable dangerous functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,file_get_contents

; Error handling
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"
session.use_strict_mode = 1
session.sid_length = 32
session.gc_maxlifetime = 1800  ; 30 minutes

; File upload
upload_max_filesize = 10M
post_max_size = 10M
file_uploads = On

; MySQL security
mysqli.allow_persistent = Off

; Code execution
allow_url_fopen = Off
allow_url_include = Off
</tml>
```

---

## 🔍 Verification Checklist

- [ ] Database migration berhasil dijalankan
- [ ] Semua new tables tercipta:
  - [ ] `security_logs`
  - [ ] `api_rate_limits`
  - [ ] `password_history`
  - [ ] `audit_logs`
  - [ ] `encryption_keys`
  - [ ] `session_whitelist`
- [ ] New columns di `users` table:
  - [ ] `locked_until`
  - [ ] `last_login`
  - [ ] `failed_login_attempts`
  - [ ] `requires_password_change`
  - [ ] `two_factor_enabled`
  - [ ] `two_factor_secret`
- [ ] File `lib/security.php` exists
- [ ] All test cases passed ✅
- [ ] Security headers present
- [ ] Log files accessible
- [ ] Cron job configured for log cleanup

---

## 🚨 Troubleshooting

### Error: "CALL cleanup_old_logs() tidak ada procedure"
```sql
-- Recreate procedure
DELIMITER //
DROP PROCEDURE IF EXISTS cleanup_old_logs //
CREATE PROCEDURE cleanup_old_logs()
BEGIN
  DELETE FROM security_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);
  DELETE FROM audit_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY);
  DELETE FROM api_rate_limits WHERE last_request < DATE_SUB(NOW(), INTERVAL 7 DAY);
END //
DELIMITER ;
```

### Error: "Table 'email_dispatcher.security_logs' doesn't exist"
```sql
-- Re-run migration
mysql -u root email_dispatcher < db/migration_security_enhancements.sql
```

### Error: "Undefined function SecurityManager"
```php
// Ensure this line exists in your PHP file:
require_once __DIR__ . '/../lib/security.php';

// And initialize it:
$pdo = DB::conn();
SecurityManager::init($pdo);
```

### Session timeout tidak bekerja
```php
// Check php.ini setting:
// session.gc_maxlifetime = 1800 (dalam seconds, default 24 menit)

// Atau set di code:
ini_set('session.gc_maxlifetime', 1800);
```

---

## 📚 Documentation Files

- `SECURITY_ENHANCEMENTS.md` - Complete feature list
- `lib/security.php` - SecurityManager class
- `db/migration_security_enhancements.sql` - Database migration

---

## 📞 Support

Untuk pertanyaan atau issues, silakan:
1. Cek log files di `storage/logs/`
2. Cek security_logs di MySQL
3. Review error_log di PHP error logs

---

**Last Updated**: March 6, 2026  
**Version**: 1.0  
**Status**: ✅ Production Ready
