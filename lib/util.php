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
 * Get Microsoft Graph API access token using Client Credentials Flow
 * @return string|false Access token or false on failure
 */
function get_graph_access_token() {
    $tenantId = GRAPH_TENANT_ID;
    $clientId = GRAPH_CLIENT_ID;
    $clientSecret = GRAPH_CLIENT_SECRET;
    
    // Check if credentials are configured
    if (empty($tenantId) || empty($clientId) || empty($clientSecret)) {
        error_log('Graph API credentials not configured');
        return false;
    }
    
    $url = "https://login.microsoftonline.com/" . urlencode($tenantId) . "/oauth2/v2.0/token";
    
    $postData = [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'scope' => 'https://graph.microsoft.com/.default'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log('Graph API token request failed: ' . $error);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log('Graph API token request HTTP error: ' . $httpCode . ' Response: ' . $response);
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['access_token'])) {
        error_log('Graph API token response missing access_token: ' . $response);
        return false;
    }
    
    return $data['access_token'];
}

/**
 * Send email via Microsoft Graph API
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param array $attachments Array of attachment file paths
 * @param string $cc CC recipients (semicolon separated)
 * @return array ['success' => bool, 'message' => string]
 */
function send_email_via_graph($to, $subject, $body, $attachments = [], $cc = '') {
    $accessToken = get_graph_access_token();
    
    if (!$accessToken) {
        return ['success' => false, 'message' => 'Failed to get Graph API access token'];
    }
    
    // Build recipients array
    $recipients = [];
    $toEmails = explode(';', $to);
    foreach ($toEmails as $email) {
        $email = trim($email);
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = ['emailAddress' => ['address' => $email]];
        }
    }
    
    // Build CC recipients
    $ccRecipients = [];
    if (!empty($cc)) {
        $ccEmails = explode(';', $cc);
        foreach ($ccEmails as $email) {
            $email = trim($email);
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $ccRecipients[] = ['emailAddress' => ['address' => $email]];
            }
        }
    }
    
    // Build attachments array
    $graphAttachments = [];
    foreach ($attachments as $filePath) {
        if (!file_exists($filePath)) {
            continue;
        }
        
        $fileName = basename($filePath);
        $fileContent = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);
        
        $graphAttachments[] = [
            '@odata.type' => '#microsoft.graph.fileAttachment',
            'name' => $fileName,
            'contentBytes' => base64_encode($fileContent),
            'contentType' => $mimeType
        ];
    }
    
    // Build email message
    $message = [
        'subject' => $subject,
        'body' => [
            'contentType' => 'HTML',
            'content' => $body
        ],
        'toRecipients' => $recipients
    ];
    
    if (!empty($ccRecipients)) {
        $message['ccRecipients'] = $ccRecipients;
    }
    
    if (!empty($graphAttachments)) {
        $message['attachments'] = $graphAttachments;
    }
    
    // Send via Graph API
    $url = "https://graph.microsoft.com/v1.0/users/" . urlencode(get_sender_account()) . "/sendMail";
    
    $data = ['message' => $message];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log('Graph API send email failed: ' . $error);
        return ['success' => false, 'message' => 'Curl error: ' . $error];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'message' => 'Email sent successfully via Graph API'];
    }
    
    error_log('Graph API send email HTTP error: ' . $httpCode . ' Response: ' . $response);
    return ['success' => false, 'message' => 'HTTP error: ' . $httpCode . ' - ' . $response];
}

/**
 * Send email via SMTP
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param array $attachments Array of attachment file paths
 * @param string $cc CC recipients (semicolon separated)
 * @return array ['success' => bool, 'message' => string]
 */
function send_email_via_smtp($to, $subject, $body, $attachments = [], $cc = '') {
    // Check SMTP configuration
    if (empty(SMTP_HOST) || empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
        return ['success' => false, 'message' => 'SMTP configuration incomplete. Please configure SMTP settings.'];
    }
    
    // Generate boundary for multipart
    $boundary = md5(time());
    
    // Build headers
    $fromEmail = SMTP_FROM_EMAIL ?: SMTP_USERNAME;
    $fromName = SMTP_FROM_NAME ?: 'Email Dispatcher';
    
    $headers = [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Build body
    $message = '';
    
    // HTML body part
    $message .= '--' . $boundary . "\r\n";
    $message .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $message .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
    $message .= chunk_split(base64_encode($body)) . "\r\n";
    
    // Add attachments
    foreach ($attachments as $filePath) {
        if (!file_exists($filePath)) {
            continue;
        }
        
        $fileName = basename($filePath);
        $fileContent = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);
        
        $message .= '--' . $boundary . "\r\n";
        $message .= 'Content-Type: ' . $mimeType . '; name="' . $fileName . '"' . "\r\n";
        $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
        $message .= 'Content-Disposition: attachment; filename="' . $fileName . '"' . "\r\n\r\n";
        $message .= chunk_split(base64_encode($fileContent)) . "\r\n";
    }
    
    $message .= '--' . $boundary . '--';
    
    // Build recipient list
    $toEmails = [];
    $toEmailList = explode(';', $to);
    foreach ($toEmailList as $email) {
        $email = trim($email);
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $toEmails[] = $email;
        }
    }
    
    $toHeader = implode(', ', $toEmails);
    $headers[] = 'To: ' . $toHeader;
    
    // Add CC if provided
    if (!empty($cc)) {
        $ccEmails = [];
        $ccEmailList = explode(';', $cc);
        foreach ($ccEmailList as $email) {
            $email = trim($email);
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $ccEmails[] = $email;
            }
        }
        if (!empty($ccEmails)) {
            $headers[] = 'Cc: ' . implode(', ', $ccEmails);
        }
    }
    
    $headers[] = 'Subject: ' . $subject;
    
    // Send email
    $headersStr = implode("\r\n", $headers);
    
    // Set encryption
    $encryption = strtolower(SMTP_ENCRYPTION);
    if ($encryption === 'ssl') {
        $host = 'ssl://' . SMTP_HOST;
    } else {
        $host = SMTP_HOST;
    }
    
    try {
        $fp = fsockopen($host, SMTP_PORT, $errno, $errstr, 30);
        
        if (!$fp) {
            return ['success' => false, 'message' => 'Failed to connect to SMTP server: ' . $errstr];
        }
        
        // Read greeting
        fgets($fp, 512);
        
        // Send EHLO
        fwrite($fp, 'EHLO ' . gethostname() . "\r\n");
        fgets($fp, 512);
        fgets($fp, 512);
        
        // Start TLS if needed
        if ($encryption === 'tls') {
            fwrite($fp, 'STARTTLS' . "\r\n");
            fgets($fp, 512);
            
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                return ['success' => false, 'message' => 'Failed to enable TLS encryption'];
            }
            
            // Send EHLO again after TLS
            fwrite($fp, 'EHLO ' . gethostname() . "\r\n");
            fgets($fp, 512);
            fgets($fp, 512);
        }
        
        // Authenticate
        fwrite($fp, 'AUTH LOGIN' . "\r\n");
        fgets($fp, 512);
        
        fwrite($fp, base64_encode(SMTP_USERNAME) . "\r\n");
        fgets($fp, 512);
        
        fwrite($fp, base64_encode(SMTP_PASSWORD) . "\r\n");
        $response = fgets($fp, 512);
        
        if (strpos($response, '235') === false) {
            fclose($fp);
            return ['success' => false, 'message' => 'SMTP authentication failed'];
        }
        
        // Send MAIL FROM
        fwrite($fp, 'MAIL FROM: <' . $fromEmail . '>' . "\r\n");
        fgets($fp, 512);
        
        // Send RCPT TO for each recipient
        foreach ($toEmails as $email) {
            fwrite($fp, 'RCPT TO: <' . $email . '>' . "\r\n");
            fgets($fp, 512);
        }
        
        // Send DATA
        fwrite($fp, 'DATA' . "\r\n");
        fgets($fp, 512);
        
        // Send headers and body
        fwrite($fp, $headersStr . "\r\n\r\n");
        fwrite($fp, $message . "\r\n");
        fwrite($fp, '.' . "\r\n");
        
        $response = fgets($fp, 512);
        
        // Quit
        fwrite($fp, 'QUIT' . "\r\n");
        fclose($fp);
        
        if (strpos($response, '250') !== false) {
            return ['success' => true, 'message' => 'Email sent successfully via SMTP'];
        } else {
            return ['success' => false, 'message' => 'SMTP send failed: ' . $response];
        }
        
    } catch (Exception $e) {
        error_log('SMTP send email failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'SMTP error: ' . $e->getMessage()];
    }
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
 * ADMIN PASSWORD VERIFICATION
 * ============================================
 */

/**
 * Verify admin password for sensitive operations
 * @param string $password Password to verify
 * @param PDO $pdo Database connection
 * @return bool True if password is correct
 */
function verify_admin_password($password, $pdo) {
    if (empty($password)) {
        return false;
    }
    
    // Get current user from session
    if (!isset($_SESSION['user']['id'])) {
        return false;
    }
    
    $userId = $_SESSION['user']['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Verify password using password_verify
        return password_verify($password, $user['password']);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Require admin password for sensitive operations
 * Dies with error message if password is invalid
 * @param string $passwordField POST field name for password (default: 'admin_password')
 * @param PDO $pdo Database connection
 * @param string $redirectTo URL to redirect on failure (optional)
 */
function require_admin_password($passwordField = 'admin_password', $pdo, $redirectTo = null) {
    $password = $_POST[$passwordField] ?? '';
    
    if (!verify_admin_password($password, $pdo)) {
        if ($redirectTo) {
            $_SESSION['error'] = 'Password administrator tidak valid. Operasi dibatalkan.';
            header('Location: ' . $redirectTo);
            exit;
        }
        http_response_code(403);
        die('Password administrator tidak valid. Operasi dibatalkan.');
    }
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
