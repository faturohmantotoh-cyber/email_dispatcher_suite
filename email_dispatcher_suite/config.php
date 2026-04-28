<?php
session_start();
// Basic configuration for Laragon
// Adjust DB credentials to your local setup
if(empty($_SESSION['user'])){
    header('Location: login.php');
    exit;
}

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

?>