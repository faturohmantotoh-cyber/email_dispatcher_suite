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
?>
