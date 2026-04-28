<?php
/**
 * api_templates.php - API untuk manajemen email templates
 * Endpoints:
 * GET  /api_templates.php?action=list                    - List all templates
 * GET  /api_templates.php?action=get&id=1                - Get template detail
 * GET  /api_templates.php?action=get_by_group&group_id=1 - Get template by group
 * POST /api_templates.php?action=create                  - Create new template
 * POST /api_templates.php?action=update&id=1             - Update template
 * POST /api_templates.php?action=delete&id=1             - Delete template
 * POST /api_templates.php?action=link_group              - Link template to group
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';
require_once __DIR__ . '/../lib/security.php';

header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (empty($_SESSION['user'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$pdo = DB::conn();
SecurityManager::init($pdo);

// Get action from GET or POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ===== LIST ALL TEMPLATES =====
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT id, name, description, template_type, created_by, created_at 
            FROM email_templates 
            ORDER BY created_at DESC
        ");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        exit(json_encode(['ok' => true, 'data' => $templates]));
    } catch (Exception $e) {
        http_response_code(500);
        exit(json_encode(['error' => $e->getMessage()]));
    }
}

// ===== GET TEMPLATE DETAIL =====
if ($action === 'get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $templateId = (int)($_GET['id'] ?? 0);
    
    if ($templateId <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid template ID']));
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            http_response_code(404);
            exit(json_encode(['error' => 'Template not found']));
        }
        
        http_response_code(200);
        exit(json_encode(['ok' => true, 'data' => $template]));
    } catch (Exception $e) {
        http_response_code(500);
        exit(json_encode(['error' => $e->getMessage()]));
    }
}

// ===== GET TEMPLATE BY GROUP =====
if ($action === 'get_by_group' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $groupId = (int)($_GET['group_id'] ?? 0);
    
    if ($groupId <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid group ID']));
    }
    
    try {
        // Get linked template for this group
        $stmt = $pdo->prepare("
            SELECT et.id, et.name, et.body, et.description 
            FROM email_templates et
            INNER JOIN template_group_links tgl ON et.id = tgl.template_id
            WHERE tgl.group_id = ?
            LIMIT 1
        ");
        $stmt->execute([$groupId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        exit(json_encode(['ok' => true, 'data' => $template]));
    } catch (Exception $e) {
        http_response_code(500);
        exit(json_encode(['error' => $e->getMessage()]));
    }
}

// ===== GET TEMPLATE BY GROUP ORDER =====
if ($action === 'get_by_group_order' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $groupOrderId = (int)($_GET['group_order_id'] ?? 0);
    
    if ($groupOrderId <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid group order ID']));
    }
    
    try {
        // Get linked template for this group order
        $stmt = $pdo->prepare("
            SELECT et.id, et.name, et.body, et.description 
            FROM email_templates et
            INNER JOIN template_group_order_links tgol ON et.id = tgol.template_id
            WHERE tgol.group_order_id = ?
            LIMIT 1
        ");
        $stmt->execute([$groupOrderId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        exit(json_encode(['ok' => true, 'data' => $template]));
    } catch (Exception $e) {
        http_response_code(500);
        exit(json_encode(['error' => $e->getMessage()]));
    }
}

// ===== CREATE NEW TEMPLATE (Admin Only) =====
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasRole('admin')) {
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden']));
    }
    
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit(json_encode(['error' => 'CSRF token invalid']));
    }
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $body = $_POST['body'] ?? '';
    $templateType = $_POST['template_type'] ?? 'standalone';
    
    if (empty($name) || empty($body)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Name and body required']));
    }
    
    if (!in_array($templateType, ['standalone', 'group', 'group_order'])) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid template type']));
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_templates (name, description, body, template_type, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $body, $templateType, $_SESSION['user']['id']]);
        
        $templateId = $pdo->lastInsertId();
        
        SecurityManager::logSecurityEvent('template_created', $_SESSION['user']['id'], "Created template: $name");
        
        http_response_code(201);
        exit(json_encode(['ok' => true, 'id' => $templateId]));
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            http_response_code(409);
            exit(json_encode(['error' => 'Template name already exists']));
        }
        http_response_code(500);
        exit(json_encode(['error' => $e->getMessage()]));
    }
}

// ===== UPDATE TEMPLATE (Admin Only) =====
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasRole('admin')) {
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden']));
    }
    
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit(json_encode(['error' => 'CSRF token invalid']));
    }
    
    $templateId = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $body = $_POST['body'] ?? '';
    $templateType = $_POST['template_type'] ?? 'standalone';
    
    if ($templateId <= 0 || empty($name) || empty($body)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid parameters']));
    }
    
    if (!in_array($templateType, ['standalone', 'group', 'group_order'])) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid template type']));
    }
    
    try {
        // Check if exists
        $check = $pdo->prepare("SELECT id FROM email_templates WHERE id = ?");
        $check->execute([$templateId]);
        
        if (!$check->fetch()) {
            http_response_code(404);
            exit(json_encode(['error' => 'Template not found']));
        }
        
        $stmt = $pdo->prepare("
            UPDATE email_templates 
            SET name = ?, description = ?, body = ?, template_type = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $body, $templateType, $templateId]);
        
        SecurityManager::logSecurityEvent('template_updated', $_SESSION['user']['id'], "Updated template ID: $templateId");
        
        http_response_code(200);
        exit(json_encode(['ok' => true]));
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            http_response_code(409);
            exit(json_encode(['error' => 'Template name already exists']));
        }
        http_response_code(500);
        exit(json_encode(['error' => $e->getMessage()]));
    }
}

// ===== DELETE TEMPLATE (Admin Only) =====
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasRole('admin')) {
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden']));
    }
    
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit(json_encode(['error' => 'CSRF token invalid']));
    }
    
    $templateId = (int)($_POST['id'] ?? 0);
    
    if ($templateId <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid template ID']));
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM email_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        
        SecurityManager::logSecurityEvent('template_deleted', $_SESSION['user']['id'], "Deleted template ID: $templateId");
        
        http_response_code(200);
        exit(json_encode(['ok' => true]));
    } catch (Exception $e) {
        http_response_code(500);
        exit(json_encode(['error' => $e->getMessage()]));
    }
}

// ===== LINK TEMPLATE TO GROUP (Admin Only) =====
if ($action === 'link_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasRole('admin')) {
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden']));
    }
    
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit(json_encode(['error' => 'CSRF token invalid']));
    }
    
    $templateId = (int)($_POST['template_id'] ?? 0);
    $groupId = (int)($_POST['group_id'] ?? 0);
    
    if ($groupId <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid group ID']));
    }
    
    try {
        // Remove any existing links for this group
        $deleteStmt = $pdo->prepare("DELETE FROM template_group_links WHERE group_id = ?");
        $deleteStmt->execute([$groupId]);
        
        if ($templateId > 0) {
            // Add new link when checked
            $stmt = $pdo->prepare(" 
                INSERT INTO template_group_links (template_id, group_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$templateId, $groupId]);

            SecurityManager::logSecurityEvent('template_linked', $_SESSION['user']['id'], "Linked template $templateId to group $groupId");
        } else {
            // Unchecked state means unlink only
            SecurityManager::logSecurityEvent('template_unlinked', $_SESSION['user']['id'], "Unlinked template from group $groupId");
        }
        
        http_response_code(200);
        exit(json_encode(['ok' => true]));
    } catch (Exception $e) {
        http_response_code(500);
        exit(json_encode(['error' => $e->getMessage()]));
    }
}

// ===== LINK TEMPLATE TO GROUP ORDER (Admin Only) =====
if ($action === 'link_group_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasRole('admin')) {
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden']));
    }
    
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit(json_encode(['error' => 'CSRF token invalid']));
    }
    
    $templateId = (int)($_POST['template_id'] ?? 0);
    $groupOrderId = (int)($_POST['group_order_id'] ?? 0);
    
    if ($groupOrderId <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid group order ID']));
    }
    
    try {
        // Remove any existing links for this group order
        $deleteStmt = $pdo->prepare("DELETE FROM template_group_order_links WHERE group_order_id = ?");
        $deleteStmt->execute([$groupOrderId]);
        
        if ($templateId > 0) {
            // Add new link when checked
            $stmt = $pdo->prepare(" 
                INSERT INTO template_group_order_links (template_id, group_order_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$templateId, $groupOrderId]);

            SecurityManager::logSecurityEvent('template_linked', $_SESSION['user']['id'], "Linked template $templateId to group order $groupOrderId");
        } else {
            // Unchecked state means unlink only
            SecurityManager::logSecurityEvent('template_unlinked', $_SESSION['user']['id'], "Unlinked template from group order $groupOrderId");
        }
        
        http_response_code(200);
        exit(json_encode(['ok' => true]));
    } catch (Exception $e) {
        http_response_code(500);
        exit(json_encode(['error' => $e->getMessage()]));
    }
}

// Invalid action
http_response_code(400);
exit(json_encode(['error' => 'Invalid action']));

?>
