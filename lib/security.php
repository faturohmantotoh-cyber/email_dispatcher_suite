<?php
/**
 * security.php - Security utilities and hardening functions
 * Provides: CSRF tokens, rate limiting, session security, input validation
 */

class SecurityManager {
    private static $pdo = null;

    public static function init($pdo) {
        self::$pdo = $pdo;
        self::setSecurityHeaders();
        self::hardenSession();
    }

    /**
     * Set HTTP security headers to prevent common attacks
     */
    public static function setSecurityHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN', true);
        
        // Prevent MIME sniffing
        header('X-Content-Type-Options: nosniff', true);
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block', true);
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin', true);
        
        // Content Security Policy (strict)
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; font-src 'self'; img-src 'self' data:; connect-src 'self'", true);
        
        // Feature policy / Permissions policy
        header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()", true);
        
        // HSTS (if HTTPS)
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload', true);
        }
    }

    /**
     * Harden session security
     */
    public static function hardensession() {
        // Regenerate session ID on login
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user']) && empty($_SESSION['session_regenerated'])) {
            session_regenerate_id(true);
            $_SESSION['session_regenerated'] = true;
        }

        // Set secure session cookies (only if session not yet started)
        if (session_status() === PHP_SESSION_NONE) {
            $cookieOptions = [
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax'
            ];
            session_set_cookie_params($cookieOptions);
        }

        // Session timeout (30 minutes)
        if (!empty($_SESSION['user']) && !empty($_SESSION['login_time'])) {
            $timeout = 1800; // 30 minutes
            if (time() - $_SESSION['login_time'] > $timeout) {
                session_destroy();
                header('Location: login.php?timeout=1');
                exit;
            }
        }
        
        if (!empty($_SESSION['user'])) {
            $_SESSION['login_time'] = time();
        }
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token']) || (time() - ($_SESSION['csrf_time'] ?? 0) > 3600)) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        return ($token ?? '') === ($_SESSION['csrf_token'] ?? '') && 
               (time() - ($_SESSION['csrf_time'] ?? 0) <= 3600);
    }

    /**
     * Rate limiting check
     * @param string $key Unique identifier (IP, user ID, etc)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if allowed, false if rate limited
     */
    public static function checkRateLimit($key, $maxAttempts = 5, $timeWindow = 300) {
        if (self::$pdo === null) return true; // Skip if DB not available
        
        try {
            $stmt = self::$pdo->prepare("
                SELECT COUNT(*) as attempts FROM security_logs 
                WHERE action_key = ? AND timestamp > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$key, $timeWindow]);
            $result = $stmt->fetch();
            
            return ($result['attempts'] ?? 0) < $maxAttempts;
        } catch (Exception $e) {
            return true; // Allow on error
        }
    }

    /**
     * Log security event
     * @param string $type Type of event (login_attempt, failed_login, suspicious, etc)
     * @param string $key Unique identifier
     * @param string $details Additional details
     */
    public static function logSecurityEvent($type, $key, $details = null) {
        if (self::$pdo === null) return;
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO security_logs (event_type, action_key, details, ip_address, user_agent, timestamp) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $type,
                $key,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Silent fail
        }
    }

    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 254;
    }

    /**
     * Validate password strength
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validatePassword($password) {
        if (strlen($password) < 12) {
            return ['valid' => false, 'message' => 'Password minimal 12 karakter'];
        }
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'Password harus mengandung huruf kecil'];
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Password harus mengandung huruf besar'];
        }
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password harus mengandung angka'];
        }
        if (!preg_match('/[!@#$%^&*]/', $password)) {
            return ['valid' => false, 'message' => 'Password harus mengandung karakter spesial (!@#$%^&*)'];
        }
        return ['valid' => true, 'message' => 'Password kuat'];
    }

    /**
     * Sanitize filename to prevent directory traversal
     */
    public static function sanitizeFilename($filename) {
        // Remove path components
        $filename = basename($filename);
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        return $filename;
    }

    /**
     * Validate file upload
     * @param array $file $_FILES array element
     * @param array $allowedMimes Allowed MIME types
     * @param int $maxSize Max file size in bytes
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateFileUpload($file, $allowedMimes = [], $maxSize = 5242880) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error'];
        }

        if (!file_exists($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'File tidak ditemukan'];
        }

        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File terlalu besar'];
        }

        if (!empty($allowedMimes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimes)) {
                return ['valid' => false, 'error' => 'Format file tidak diizinkan'];
            }
        }

        return ['valid' => true];
    }

    /**
     * Get client IP address (handles proxies)
     */
    public static function getClientIP() {
        $ipKeys = ['CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Escape user input (stronger than htmlspecialchars)
     */
    public static function escape($string) {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Create secure password reset token
     */
    public static function generatePasswordResetToken() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Verify account lockout status
     * @param int $userId User ID
     * @return bool True if account is locked
     */
    public static function isAccountLocked($userId) {
        if (self::$pdo === null) return false;
        
        try {
            $stmt = self::$pdo->prepare("
                SELECT COUNT(*) as failed_attempts FROM security_logs 
                WHERE action_key = ? AND event_type = 'failed_login' 
                AND timestamp > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            
            return ($result['failed_attempts'] ?? 0) >= 5;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Lock account temporarily
     */
    public static function lockAccount($userId) {
        if (self::$pdo === null) return;
        
        try {
            $stmt = self::$pdo->prepare("UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            // Silent fail
        }
    }
}

?>
