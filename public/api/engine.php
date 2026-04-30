<?php
/**
 * Client Engine API Endpoints
 * API untuk Python Email Sender Engine
 * 
 * Endpoints:
 * - GET /api/engine/fetch-queue - Fetch pending emails
 * - POST /api/engine/report-status - Report email send status
 * - GET /api/engine/validate-token - Validate engine token
 * - POST /api/engine/queue-email - Add email to queue (from web app)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/util.php';

header('Content-Type: application/json');

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Validate token from header
function validateToken($pdo) {
    $token = $_SERVER['HTTP_X_ENGINE_TOKEN'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    // Remove Bearer prefix if present
    $token = str_replace('Bearer ', '', $token);
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token required']);
        exit;
    }
    
    // Check token in database
    $stmt = $pdo->prepare("
        SELECT t.*, u.id as user_id, u.username, u.email, u.display_name, u.role
        FROM user_api_tokens t
        JOIN users u ON t.user_id = u.id
        WHERE t.token = ? AND t.is_active = 1
        AND (t.expires_at IS NULL OR t.expires_at > NOW())
    ");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();
    
    if (!$tokenData) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }
    
    // Update last used
    $stmt = $pdo->prepare("
        UPDATE user_api_tokens 
        SET last_used_at = NOW(), ip_address = ?, user_agent = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $tokenData['id']
    ]);
    
    return $tokenData;
}

// Log engine activity
function logEngineActivity($pdo, $tokenId, $userId, $action, $emailQueueId = null, $status = null, $message = null) {
    $engineId = $_SERVER['HTTP_X_ENGINE_ID'] ?? null;
    $engineVersion = $_SERVER['HTTP_X_ENGINE_VERSION'] ?? null;
    
    $stmt = $pdo->prepare("
        INSERT INTO client_engine_logs 
        (token_id, user_id, engine_id, engine_version, action, email_queue_id, status, message, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $tokenId,
        $userId,
        $engineId,
        $engineVersion,
        $action,
        $emailQueueId,
        $status,
        $message,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

try {
    $pdo = DB::conn();
    
    switch ($action) {
        case 'fetch-queue':
            // GET /api/engine/fetch-queue?limit=10
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $tokenData = validateToken($pdo);
            $userId = $tokenData['user_id'];
            $limit = min(intval($_GET['limit'] ?? 10), 50);
            $engineId = $_SERVER['HTTP_X_ENGINE_ID'] ?? 'unknown';
            
            // Fetch pending emails for this user
            $stmt = $pdo->prepare("
                SELECT 
                    q.id,
                    q.from_email,
                    q.from_name,
                    q.to_email,
                    q.cc_email,
                    q.bcc_email,
                    q.subject,
                    q.body_html,
                    q.body_text,
                    q.attachments_json,
                    q.reply_to,
                    q.headers_json,
                    q.created_at,
                    q.scheduled_at
                FROM email_queue q
                WHERE q.user_id = ? 
                AND q.status = 'pending'
                AND (q.scheduled_at IS NULL OR q.scheduled_at <= NOW())
                ORDER BY q.priority ASC, q.created_at ASC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            $emails = $stmt->fetchAll();
            
            // Mark emails as processing
            if (!empty($emails)) {
                $emailIds = array_column($emails, 'id');
                $placeholders = implode(',', array_fill(0, count($emailIds), '?'));
                
                $stmt = $pdo->prepare("
                    UPDATE email_queue 
                    SET status = 'processing', 
                        processed_at = NOW(),
                        client_engine_id = ?
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute(array_merge([$engineId], $emailIds));
                
                // Log activity
                logEngineActivity($pdo, $tokenData['id'], $userId, 'fetch_queue', null, 'success', 
                    'Fetched ' . count($emails) . ' emails');
            }
            
            echo json_encode([
                'success' => true,
                'emails' => $emails,
                'count' => count($emails),
                'engine_id' => $engineId
            ]);
            break;
            
        case 'report-status':
            // POST /api/engine/report-status
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $tokenData = validateToken($pdo);
            $userId = $tokenData['user_id'];
            
            $input = json_decode(file_get_contents('php://input'), true);
            $results = $input['results'] ?? [];
            $engineId = $input['engine_id'] ?? 'unknown';
            
            $processed = 0;
            $failed = 0;
            
            foreach ($results as $result) {
                $emailId = $result['email_id'] ?? null;
                $success = $result['success'] ?? false;
                $message = $result['message'] ?? '';
                $sentAt = $result['sent_at'] ?? null;
                
                if ($emailId) {
                    if ($success) {
                        // Mark as sent
                        $stmt = $pdo->prepare("
                            UPDATE email_queue 
                            SET status = 'sent', 
                                sent_at = ?,
                                client_engine_id = ?,
                                error_message = NULL
                            WHERE id = ? AND user_id = ?
                        ");
                        $stmt->execute([$sentAt, $engineId, $emailId, $userId]);
                        $processed++;
                    } else {
                        // Mark as failed or retry
                        $stmt = $pdo->prepare("
                            UPDATE email_queue 
                            SET status = CASE 
                                WHEN retry_count < max_retries THEN 'pending'
                                ELSE 'failed'
                            END,
                            retry_count = retry_count + 1,
                            error_message = ?,
                            client_engine_id = ?
                            WHERE id = ? AND user_id = ?
                        ");
                        $stmt->execute([$message, $engineId, $emailId, $userId]);
                        $failed++;
                    }
                    
                    // Log activity
                    logEngineActivity($pdo, $tokenData['id'], $userId, 'report_status', 
                        $emailId, $success ? 'success' : 'failed', $message);
                }
            }
            
            echo json_encode([
                'success' => true,
                'processed' => $processed,
                'failed' => $failed,
                'total' => count($results)
            ]);
            break;
            
        case 'validate-token':
            // GET /api/engine/validate-token
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $tokenData = validateToken($pdo);
            
            // Get user settings
            $stmt = $pdo->prepare("
                SELECT outlook_account, send_delay_ms, max_batch_size, auto_check_interval_sec
                FROM client_engine_settings
                WHERE user_id = ?
            ");
            $stmt->execute([$tokenData['user_id']]);
            $settings = $stmt->fetch();
            
            logEngineActivity($pdo, $tokenData['id'], $tokenData['user_id'], 'validate_token', 
                null, 'success', 'Token validated');
            
            echo json_encode([
                'success' => true,
                'valid' => true,
                'user' => [
                    'id' => $tokenData['user_id'],
                    'username' => $tokenData['username'],
                    'email' => $tokenData['email'],
                    'display_name' => $tokenData['display_name'],
                    'role' => $tokenData['role']
                ],
                'settings' => $settings ?: null
            ]);
            break;
            
        case 'queue-email':
            // POST /api/engine/queue-email (called from web app to add to queue)
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            // This endpoint requires session authentication (from web app)
            session_start();
            if (empty($_SESSION['user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Not authenticated']);
                exit;
            }
            
            $currentUser = $_SESSION['user'];
            $input = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $pdo->prepare("
                INSERT INTO email_queue 
                (user_id, from_email, from_name, to_email, cc_email, bcc_email, 
                 subject, body_html, body_text, attachments_json, reply_to, 
                 priority, scheduled_at, job_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $currentUser['id'],
                $input['from_email'] ?? null,
                $input['from_name'] ?? null,
                json_encode($input['to_email'] ?? []),
                json_encode($input['cc_email'] ?? []),
                json_encode($input['bcc_email'] ?? []),
                $input['subject'] ?? '',
                $input['body_html'] ?? null,
                $input['body_text'] ?? null,
                json_encode($input['attachments'] ?? []),
                $input['reply_to'] ?? null,
                $input['priority'] ?? 5,
                $input['scheduled_at'] ?? null,
                $input['job_id'] ?? null
            ]);
            
            $queueId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'queue_id' => $queueId,
                'message' => 'Email added to queue'
            ]);
            break;
            
        case 'get-queue-status':
            // GET /api/engine/get-queue-status (for web app to check status)
            session_start();
            if (empty($_SESSION['user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Not authenticated']);
                exit;
            }
            
            $currentUser = $_SESSION['user'];
            
            // Get counts by status
            $stmt = $pdo->prepare("
                SELECT status, COUNT(*) as count
                FROM email_queue
                WHERE user_id = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY status
            ");
            $stmt->execute([$currentUser['id']]);
            $statusCounts = $stmt->fetchAll();
            
            // Get recent emails
            $stmt = $pdo->prepare("
                SELECT id, status, subject, to_email, created_at, sent_at, error_message
                FROM email_queue
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$currentUser['id']]);
            $recentEmails = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'status_counts' => $statusCounts,
                'recent_emails' => $recentEmails
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
    error_log('Engine API Error: ' . $e->getMessage());
}
