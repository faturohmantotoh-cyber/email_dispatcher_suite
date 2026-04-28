# SecurityManager Quick Reference

## 📚 API Documentation

### Initialization
```php
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/db.php';

$pdo = DB::conn();
SecurityManager::init($pdo);
// This sets security headers and hardens session
```

---

## 🔐 CSRF Protection

### Generate Token (in form)
```php
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <input type="text" name="username">
    <button type="submit">Submit</button>
</form>
```

### Verify Token (in handler)
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        die('Invalid CSRF token');
    }
    // Process form
}
```

---

## 🚦 Rate Limiting

### Check Rate Limit
```php
$clientIP = SecurityManager::getClientIP();
$key = 'login_attempt_' . $clientIP;

if (!SecurityManager::checkRateLimit($key, 5, 300)) {
    // 5 attempts per 300 seconds (5 minutes)
    http_response_code(429);
    die('Too many attempts');
}

SecurityManager::logSecurityEvent('login_attempt', $key, 'details');
```

### API Endpoint Example
```php
<?php
require_once '../lib/security.php';
require_once '../lib/db.php';

$pdo = DB::conn();
SecurityManager::init($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$clientIP = SecurityManager::getClientIP();
if (!SecurityManager::checkRateLimit('api_endpoint_' . $clientIP, 20, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limited']);
    exit;
}

// Process request
?>
```

---

## 🔑 Password Validation

### Validate Password Strength
```php
$password = $_POST['password'] ?? '';

$result = SecurityManager::validatePassword($password);
if (!$result['valid']) {
    echo "Error: " . $result['message'];
    // Examples:
    // "Password minimal 12 karakter"
    // "Password harus mengandung huruf besar"
    // "Password harus mengandung angka"
    // "Password harus mengandung karakter spesial (!@#$%^&*)"
} else {
    // Hash and save password
    $hash = password_hash($password, PASSWORD_BCRYPT);
}
```

### Password Requirements
- Minimal 12 karakter
- Harus mengandung: A-Z (uppercase)
- Harus mengandung: a-z (lowercase)
- Harus mengandung: 0-9 (numbers)
- Harus mengandung: !@#$%^&* (special chars)

---

## 🔍 Input Validation

### Validate Email
```php
$email = $_POST['email'] ?? '';

if (!SecurityManager::validateEmail($email)) {
    echo "Invalid email";
} else {
    // Save email
}
```

### Sanitize Filename
```php
$filename = $_FILES['upload']['name'];
$safe_filename = SecurityManager::sanitizeFilename($filename);

// Original: "../../etc/passwd.txt"
// Safe:     "etcpasswdtxt"

move_uploaded_file($_FILES['upload']['tmp_name'], '/uploads/' . $safe_filename);
```

### Validate File Upload
```php
$validation = SecurityManager::validateFileUpload(
    $_FILES['avatar'],
    ['image/jpeg', 'image/png', 'image/webp'],  // Allowed MIME types
    5242880  // Max 5MB
);

if (!$validation['valid']) {
    echo $validation['error'];
} else {
    // Process upload
}
```

---

## 📊 Security Logging

### Log Security Events
```php
// Format: $type, $action_key, $details

// Login attempt
SecurityManager::logSecurityEvent('login_attempt', $username, 'IP: ' . $clientIP);

// Failed login
SecurityManager::logSecurityEvent('failed_login', $userId, 'Invalid password');

// User creation
SecurityManager::logSecurityEvent('user_created', $admin_id, 'Created user: ' . $newUsername);

// Password change
SecurityManager::logSecurityEvent('password_changed', $userId, 'User changed password');

// Suspicious activity
SecurityManager::logSecurityEvent('suspicious', $action_key, 'Details of suspicious activity');

// Custom events
SecurityManager::logSecurityEvent('custom_event', 'identifier', 'Any event details');
```

### View Logs
```sql
-- Last 100 security events
SELECT event_type, action_key, details, ip_address, timestamp 
FROM security_logs 
ORDER BY timestamp DESC 
LIMIT 100;

-- Failed logins in last 24 hours
SELECT * FROM security_logs 
WHERE event_type = 'failed_login' 
AND timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY);

-- By IP address
SELECT event_type, COUNT(*) as count 
FROM security_logs 
WHERE ip_address = '192.168.1.100' 
GROUP BY event_type;
```

---

## 🔒 Account Lockout

### Check if Account Locked
```php
if (SecurityManager::isAccountLocked($userId)) {
    echo "Account is temporarily locked. Try again later.";
} else {
    // Allow login
}
```

### Lock Account
```php
// After 5 failed login attempts
SecurityManager::lockAccount($userId);

// Account will be locked for 15 minutes
// User can login again after lockout expires
```

---

## 🌐 Client IP Address

### Get Real Client IP
```php
$clientIP = SecurityManager::getClientIP();

// Handles:
// - Direct connections
// - Behind proxy
// - Behind CDN (Cloudflare)
// - X-Forwarded-For headers
// - IPv4 and IPv6

// Log with IP
SecurityManager::logSecurityEvent('login', $userId, 'IP: ' . $clientIP);
```

---

## 🔴 Common Security Patterns

### Complete Login Handler
```php
<?php
require_once '../config_db.php';
require_once '../lib/db.php';
require_once '../lib/security.php';

session_start();
$pdo = DB::conn();
SecurityManager::init($pdo);

// Verify CSRF
if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    $_SESSION['error'] = 'CSRF token invalid';
    exit;
}

// Rate limiting
$clientIP = SecurityManager::getClientIP();
if (!SecurityManager::checkRateLimit('login_' . $clientIP, 5, 300)) {
    SecurityManager::logSecurityEvent('rate_limit_exceeded', 'login_' . $clientIP);
    http_response_code(429);
    $_SESSION['error'] = 'Too many attempts';
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    $_SESSION['error'] = 'Username and password required';
    exit;
}

// Fetch user
$stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    // Check account lockout
    if (SecurityManager::isAccountLocked($user['id'])) {
        SecurityManager::logSecurityEvent('login_locked', $user['id']);
        $_SESSION['error'] = 'Account locked';
        exit;
    }
    
    // Successful login
    $_SESSION['user'] = ['id' => $user['id'], 'role' => $user['role']];
    $_SESSION['login_time'] = time();
    
    SecurityManager::logSecurityEvent('successful_login', $user['id'], 'IP: ' . $clientIP);
    
    header('Location: index.php');
    exit;
} else {
    // Failed login
    if ($user) {
        SecurityManager::lockAccount($user['id']);
        SecurityManager::logSecurityEvent('failed_login', $user['id']);
    } else {
        SecurityManager::logSecurityEvent('failed_login_unknown', $username);
    }
    
    $_SESSION['error'] = 'Invalid credentials';
    exit;
}
```

### Protected Form Handler
```php
<?php
require_once '../lib/security.php';
require_once '../lib/db.php';

session_start();

// Check authentication
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Initialize security
$pdo = DB::conn();
SecurityManager::init($pdo);

$pdo = DB::conn();

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    // Validate input
    $email = $_POST['email'] ?? '';
    if (!SecurityManager::validateEmail($email)) {
        die('Invalid email');
    }
    
    $name = trim($_POST['name'] ?? '');
    if (strlen($name) < 2) {
        die('Name too short');
    }
    
    // Process form
    $stmt = $pdo->prepare("INSERT INTO table (email, name) VALUES (?, ?)");
    $stmt->execute([$email, $name]);
    
    // Log change
    SecurityManager::logSecurityEvent('form_submitted', $_SESSION['user']['id'], 'User created: ' . $name);
    
    echo "Success!";
}
?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <input type="email" name="email" required>
    <input type="text" name="name" required>
    <button type="submit">Submit</button>
</form>
```

---

## ✅ Security Checklist for New Pages

When creating new pages, ensure:

- [ ] `SecurityManager::init($pdo)` called at top
- [ ] CSRF token in all forms
- [ ] CSRF token verification on POST
- [ ] Rate limiting on sensitive endpoints
- [ ] Input validation for all user inputs
- [ ] Output escaping with `e()` or `htmlspecialchars()`
- [ ] Check user authentication
- [ ] Check user authorization (role-based)
- [ ] Log security-relevant events
- [ ] No sensitive data in error messages
- [ ] No SQL injection (use prepared statements)
- [ ] No directory traversal in file operations

---

## 🐛 Debugging

### Enable Debug Mode
```php
// In security.php or config.php
define('DEBUG_MODE', true);

// Then add logging:
if (DEBUG_MODE) {
    error_log("Security event: " . json_encode($data));
}
```

### Check Database Lock
```sql
-- See if user is locked
SELECT * FROM users WHERE id = 1;
-- Check 'locked_until' column

-- Manually unlock user
UPDATE users SET locked_until = NULL WHERE id = 1;
```

### View Session Data
```php
<?php
if (DEBUG_MODE) {
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
}
?>
```

---

## 📖 References

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [NIST Cybersecurity Framework](https://www.nist.gov/cyberframework)

---

**Last Updated**: March 6, 2026  
**Version**: 1.0
