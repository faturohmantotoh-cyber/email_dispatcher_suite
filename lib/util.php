<?php
// Utility functions for Email Dispatcher Suite

/**
 * XSS Prevention - Escape HTML special characters
 * Use this for ALL user input that goes to HTML
 */
function e(string $s): string { 
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); 
}

function ensure_dirs() {
    foreach ([ATTACHMENTS_DIR, LOGS_DIR, TEMP_DIR] as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0777, true);
    }
}

function human_filesize($bytes, $decimals = 2) {
  $size = ['B','KB','MB','GB','TB','PB'];
  $factor = floor((strlen($bytes) - 1) / 3);
  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

function normalize_string($s) {
    $s = strtolower($s);
    $s = preg_replace('/\.[^.]+$/', '', $s); // remove extension
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    $s = trim($s);
    return $s;
}

function similarity_score($a, $b) {
    $a = normalize_string($a);
    $b = normalize_string($b);
    similar_text($a, $b, $percent);
    return round($percent, 2);
}

function base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    $protocol = $https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $protocol . $host . $path;
}

/**
 * Check if user has required role(s)
 * @param string|array $requiredRoles Role or array of roles to check
 * @param array|null $user User session array (uses $_SESSION['user'] if null)
 * @return bool True if user has at least one of the required roles
 */
function hasRole($requiredRoles, $user = null) {
    if ($user === null) {
        $user = $_SESSION['user'] ?? [];
    }
    
    $userRole = $user['role'] ?? 'user';
    $required = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];
    
    return in_array($userRole, $required, true);
}

/**
 * Check role and redirect if not allowed
 * @param string|array $requiredRoles Allowed role(s)
 * @param string $redirectTo URL to redirect if unauthorized
 */
function requireRole($requiredRoles, $redirectTo = 'index.php') {
    if (!hasRole($requiredRoles)) {
        header('Location: ' . $redirectTo);
        exit;
    }
}

/**
 * Get role hierarchy level (for permission checking)
 * Higher number = more permissions
 */
function getRoleLevel($role) {
    $levels = [
        'viewer' => 1,    // Can only view
        'user' => 2,      // Can send emails
        'admin' => 3      // Can manage system
    ];
    return $levels[$role] ?? 0;
}

/**
 * Check if user can perform action based on role hierarchy
 * @param string $minimumRole Minimum role level required
 * @param array|null $user User session array
 * @return bool
 */
function canPerform($minimumRole, $user = null) {
    if ($user === null) {
        $user = $_SESSION['user'] ?? [];
    }
    
    $userRole = $user['role'] ?? 'user';
    $userLevel = getRoleLevel($userRole);
    $minimumLevel = getRoleLevel($minimumRole);
    
    return $userLevel >= $minimumLevel;
}

/**
 * Validate and sanitize email input
 * @param string $email Email to validate
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 254;
}

/**
 * Sanitize JSON string for safe output
 * @param string $string JSON string
 * @return string Escaped JSON string
 */
function escapeJSON($string) {
    return json_encode($string);
}

/**
 * Get safe pagination limit
 * @param int $requested Requested limit
 * @param int $max Maximum allowed limit
 * @param int $default Default limit if invalid
 * @return int Safe limit value
 */
function getSafeLimit($requested, $max = 100, $default = 20) {
    $limit = (int)$requested;
    if ($limit <= 0 || $limit > $max) {
        return $default;
    }
    return $limit;
}

/**
 * Safe file downloads - prevent directory traversal
 * @param string $filepath Path to file
 * @return bool Validation result
 */
function isValidFileDownload($filepath) {
    $realpath = realpath($filepath);
    $basedir = realpath(__DIR__ . '/../storage');
    
    return $realpath && $basedir && strpos($realpath, $basedir) === 0 && file_exists($realpath);
}

/**
 * Groq AI-powered similarity scoring
 * Uses Llama model to intelligently match filenames to recipient names/emails
 * @param string $filename Attachment filename
 * @param string $recipientName Recipient display name
 * @param string $recipientEmail Recipient email address
 * @return float Similarity score 0-100
 */
function groq_similarity_score($filename, $recipientName, $recipientEmail) {
    $apiKey = GROQ_API_KEY;
    
    // If no API key, return 0 (will fallback to classic similarity)
    if (empty($apiKey)) {
        return 0;
    }
    
    $prompt = "Rate similarity 0-100 between filename '$filename' and recipient name '$recipientName' / email '$recipientEmail'. Consider name variations, initials, and semantic meaning. Return only the number.";
    
    $ch = curl_init(GROQ_API_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => GROQ_MODEL,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.1,
        'max_tokens' => 10
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        // On error, return 0 to fallback to classic similarity
        error_log("Groq API error: HTTP $httpCode, Response: $response");
        return 0;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        error_log("Groq API invalid response: $response");
        return 0;
    }
    
    $score = (float)trim($data['choices'][0]['message']['content']);
    
    // Ensure score is between 0-100
    if ($score < 0) $score = 0;
    if ($score > 100) $score = 100;
    
    return round($score, 2);
}

/**
 * Enhanced similarity scoring with Groq AI fallback
 * @param string $a First string (filename)
 * @param string $b Second string (recipient name/email)
 * @param string $email Optional email for better AI matching
 * @param bool $useAI Whether to use Groq AI (default: true if API key available)
 * @return float Similarity score 0-100
 */
function similarity_score_enhanced($a, $b, $email = '', $useAI = true) {
    // Try Groq AI first if enabled and API key available
    if ($useAI && !empty(GROQ_API_KEY)) {
        $aiScore = groq_similarity_score($a, $b, $email);
        if ($aiScore > 0) {
            return $aiScore;
        }
    }
    
    // Fallback to classic similarity
    return similarity_score($a, $b);
}
/**
 * ============================================
 * CSRF PROTECTION FUNCTIONS
 * ============================================
 */

/**
 * Generate and store CSRF token in session
 * @return string CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get current CSRF token (generate if not exists)
 * @return string CSRF token
 */
function get_csrf_token() {
    return generate_csrf_token();
}

/**
 * Validate CSRF token from POST request
 * @param string $token Token to validate (defaults to $_POST['csrf_token'])
 * @return bool True if valid
 */
function validate_csrf_token($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF token and die if invalid (for form processing)
 * @param string $redirectTo URL to redirect on failure
 */
function require_csrf_token($redirectTo = null) {
    if (!validate_csrf_token()) {
        if ($redirectTo) {
            header('Location: ' . $redirectTo);
            exit;
        }
        http_response_code(403);
        die('Invalid or missing CSRF token. Please refresh the page and try again.');
    }
}

/**
 * Output CSRF token hidden field for forms
 * @return string HTML input field
 */
function csrf_field() {
    $token = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * ============================================
 * RATE LIMITING FUNCTIONS
 * ============================================
 */

/**
 * Check and enforce rate limiting
 * @param string $key Unique identifier (user_id, ip_address, or action_key)
 * @param int $maxRequests Maximum requests allowed in time window
 * @param int $windowSeconds Time window in seconds
 * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
 */
function check_rate_limit($key, $maxRequests = 100, $windowSeconds = 3600) {
    $rateLimitKey = 'rate_limit_' . md5($key);
    $now = time();
    
    // Initialize or get current bucket
    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = [
            'count' => 0,
            'window_start' => $now
        ];
    }
    
    $bucket = &$_SESSION[$rateLimitKey];
    
    // Reset if window expired
    if ($now - $bucket['window_start'] > $windowSeconds) {
        $bucket = [
            'count' => 0,
            'window_start' => $now
        ];
    }
    
    // Check if allowed
    $allowed = $bucket['count'] < $maxRequests;
    $remaining = max(0, $maxRequests - $bucket['count']);
    $reset = $bucket['window_start'] + $windowSeconds;
    
    if ($allowed) {
        $bucket['count']++;
    }
    
    return [
        'allowed' => $allowed,
        'remaining' => $remaining,
        'reset' => $reset,
        'limit' => $maxRequests,
        'window' => $windowSeconds
    ];
}

/**
 * Check rate limit and enforce for current user
 * @param string $action Action name (e.g., 'email_send', 'contact_create')
 * @param int $maxRequests Max requests per window
 * @param int $windowSeconds Window in seconds
 * @param bool $dieOnLimit Whether to die with error or return result
 * @return bool|array False if blocked, true if allowed (or array if !$dieOnLimit)
 */
function rate_limit_user($action = 'default', $maxRequests = 100, $windowSeconds = 3600, $dieOnLimit = true) {
    $userId = $_SESSION['user']['id'] ?? 'guest_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = $userId . '_' . $action;
    
    $result = check_rate_limit($key, $maxRequests, $windowSeconds);
    
    if (!$result['allowed']) {
        if ($dieOnLimit) {
            http_response_code(429);
            $retryAfter = $result['reset'] - time();
            header('Retry-After: ' . $retryAfter);
            die(json_encode([
                'error' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $retryAfter,
                'limit' => $result['limit'],
                'window' => $result['window']
            ]));
        }
        return false;
    }
    
    return $dieOnLimit ? true : $result;
}

/**
 * Get rate limit status for current user/action
 * @param string $action Action name
 * @param int $maxRequests Max requests
 * @param int $windowSeconds Window in seconds
 * @return array Rate limit status
 */
function get_rate_limit_status($action = 'default', $maxRequests = 100, $windowSeconds = 3600) {
    $userId = $_SESSION['user']['id'] ?? 'guest_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = $userId . '_' . $action;
    return check_rate_limit($key, $maxRequests, $windowSeconds);
}

/**
 * ============================================
 * SECURITY HEADERS & SESSION HARDENING
 * ============================================
 */

/**
 * Set secure session and security headers
 * Call this at the beginning of each request
 */
function set_security_headers() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevent MIME sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy (adjust as needed)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self';");
    
    // HSTS (enable only if using HTTPS)
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/**
 * Harden session configuration
 * Call BEFORE session_start()
 */
function harden_session() {
    // Only set ini settings if session hasn't started yet
    if (session_status() === PHP_SESSION_NONE) {
        // Use secure cookies if HTTPS
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
        
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_lifetime', '0'); // Session cookie
        ini_set('session.gc_maxlifetime', '3600'); // 1 hour
    }
}

/**
 * Initialize security (call at app startup)
 */
function init_security() {
    set_security_headers();
    harden_session();
    
    // Regenerate session ID periodically to prevent fixation
    if (!empty($_SESSION['last_regeneration']) && time() - $_SESSION['last_regeneration'] > 900) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    } elseif (empty($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    }
}

?>
