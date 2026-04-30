<?php
// Load utilities first
require_once __DIR__ . '/lib/util.php';

// Harden session settings BEFORE starting session
harden_session();

// Now start session
session_start();

// Basic configuration for Laragon
// Adjust DB credentials to your local setup
if(empty($_SESSION['user'])){
    header('Location: login.php');
    exit;
}

// Initialize security headers and CSRF (after session is active)
init_security();

require_once __DIR__ . '/config_db.php';

define('APP_TIMEZONE', 'Asia/Jakarta');

date_default_timezone_set(APP_TIMEZONE);

// Absolute base path
define('BASE_PATH', __DIR__);

define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');

define('ATTACHMENTS_DIR', 'D:\\email_attachment'); // Sesuaikan dengan folder lampiran Anda

define('LOGS_DIR', STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs');

define('TEMP_DIR', STORAGE_PATH . DIRECTORY_SEPARATOR . 'temp');

// Fallback account (optional): set via environment variable OUTLOOK_ACCOUNT_DEFAULT.
// Keep empty by default so account is resolved from the logged-in user.
define('OUTLOOK_ACCOUNT_DEFAULT', (string)(getenv('OUTLOOK_ACCOUNT_DEFAULT') ?: ''));

/**
 * Validate and normalize email address.
 */
function normalize_email(?string $email): string {
    $email = strtolower(trim((string)$email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

/**
 * Get the email account to use for sending
 * Uses the logged-in user's email from session, fallback to default if not available
 */
function get_sender_account() {
    $sessionUser = $_SESSION['user'] ?? [];

    // Preferred: normalized sender_email prepared during login.
    $sender = normalize_email($sessionUser['sender_email'] ?? '');
    if ($sender !== '') {
        return $sender;
    }

    // Fallback 1: account email from profile.
    $sender = normalize_email($sessionUser['email'] ?? '');
    if ($sender !== '') {
        return $sender;
    }

    // Fallback 2: username if it is an email.
    $sender = normalize_email($sessionUser['username'] ?? '');
    if ($sender !== '') {
        return $sender;
    }

    // Final fallback: optional env-based default.
    $sender = normalize_email(OUTLOOK_ACCOUNT_DEFAULT);
    if ($sender !== '') {
        return $sender;
    }

    return '';
}

// Groq AI Configuration
define('GROQ_API_KEY', (string)(getenv('GROQ_API_KEY') ?: ''));
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MODEL', 'llama3-8b-8192');

// Microsoft Graph API Configuration
define('GRAPH_TENANT_ID', (string)(getenv('GRAPH_TENANT_ID') ?: ''));
define('GRAPH_CLIENT_ID', (string)(getenv('GRAPH_CLIENT_ID') ?: ''));
define('GRAPH_CLIENT_SECRET', (string)(getenv('GRAPH_CLIENT_SECRET') ?: ''));
define('GRAPH_REDIRECT_URI', (string)(getenv('GRAPH_REDIRECT_URI') ?: 'http://localhost'));

// Email sending mode: 'outlook_com' (default), 'graph_api', or 'smtp'
// Read from database first, fallback to environment variable, then default to 'outlook_com'
function get_email_sending_mode() {
    static $cachedMode = null;
    
    if ($cachedMode !== null) {
        return $cachedMode;
    }
    
    // Try to get from database
    try {
        require_once __DIR__ . '/lib/db.php';
        $pdo = DB::conn();
        $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE `key` = 'email_sending_mode'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result && !empty($result['value'])) {
            $cachedMode = $result['value'];
            return $cachedMode;
        }
    } catch (Exception $e) {
        // Database not available yet (during installation), continue to fallback
    }
    
    // Fallback to environment variable
    $envMode = getenv('EMAIL_SENDING_MODE');
    if ($envMode) {
        $cachedMode = $envMode;
        return $cachedMode;
    }
    
    // Default to outlook_com
    $cachedMode = 'outlook_com';
    return $cachedMode;
}

define('EMAIL_SENDING_MODE', get_email_sending_mode());

// SMTP Configuration (read from database)
function get_smtp_config() {
    static $cachedConfig = null;
    
    if ($cachedConfig !== null) {
        return $cachedConfig;
    }
    
    try {
        require_once __DIR__ . '/lib/db.php';
        $pdo = DB::conn();
        
        $config = [];
        $keys = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name'];
        
        foreach ($keys as $key) {
            $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            $config[$key] = $result['value'] ?? null;
        }
        
        $cachedConfig = $config;
        return $cachedConfig;
    } catch (Exception $e) {
        // Database not available
        return [
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_username' => null,
            'smtp_password' => null,
            'smtp_encryption' => null,
            'smtp_from_email' => null,
            'smtp_from_name' => null
        ];
    }
}

// Define SMTP constants from database config
$smtpConfig = get_smtp_config();
define('SMTP_HOST', $smtpConfig['smtp_host'] ?: '');
define('SMTP_PORT', $smtpConfig['smtp_port'] ?: '587');
define('SMTP_USERNAME', $smtpConfig['smtp_username'] ?: '');
define('SMTP_PASSWORD', $smtpConfig['smtp_password'] ?: '');
define('SMTP_ENCRYPTION', $smtpConfig['smtp_encryption'] ?: 'tls');
define('SMTP_FROM_EMAIL', $smtpConfig['smtp_from_email'] ?: '');
define('SMTP_FROM_NAME', $smtpConfig['smtp_from_name'] ?: '');

?>