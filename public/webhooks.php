<?php
// webhooks.php - Webhook endpoint management
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';

ensure_dirs();
$pdo = DB::conn();

requireRole(['admin'], 'index.php');

$msg = '';
$msgType = 'info';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token('webhooks.php');
    rate_limit_user('webhook_manage', 30, 300);
    
    if (isset($_POST['add_webhook'])) {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $events = $_POST['events'] ?? [];
        
        if ($name && $url && filter_var($url, FILTER_VALIDATE_URL) && !empty($events)) {
            $secret = bin2hex(random_bytes(16));
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO webhook_endpoints (name, url, secret, events, created_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $eventsJson = json_encode($events);
                $userId = $_SESSION['user']['id'] ?? null;
                $stmt->execute([$name, $url, $secret, $eventsJson, $userId]);
                $msg = 'Webhook endpoint added successfully!';
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Error: ' . $e->getMessage();
                $msgType = 'error';
            }
        } else {
            $msg = 'Please fill all fields and select at least one event';
            $msgType = 'error';
        }
    }
    
    if (isset($_POST['toggle_webhook'])) {
        $id = intval($_POST['id'] ?? 0);
        $isActive = intval($_POST['is_active'] ?? 0);
        
        if ($id) {
            try {
                $stmt = $pdo->prepare("UPDATE webhook_endpoints SET is_active = ? WHERE id = ?");
                $stmt->execute([$isActive, $id]);
                $msg = 'Webhook status updated!';
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Error: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
    
    if (isset($_POST['delete_webhook'])) {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM webhook_endpoints WHERE id = ?");
                $stmt->execute([$id]);
                $msg = 'Webhook deleted!';
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Error: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
    
    // Test webhook
    if (isset($_POST['test_webhook'])) {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM webhook_endpoints WHERE id = ?");
            $stmt->execute([$id]);
            $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($webhook) {
                $testPayload = [
                    'event' => 'test',
                    'timestamp' => date('c'),
                    'data' => ['message' => 'This is a test webhook']
                ];
                
                $signature = hash_hmac('sha256', json_encode($testPayload), $webhook['secret']);
                
                $ch = curl_init($webhook['url']);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testPayload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'X-Webhook-Signature: sha256=' . $signature,
                    'X-Webhook-Event: test'
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                $startTime = microtime(true);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $duration = round((microtime(true) - $startTime) * 1000);
                curl_close($ch);
                
                // Log the test
                $stmt = $pdo->prepare("
                    INSERT INTO webhook_event_log (webhook_id, event_type, payload, response_code, response_body, duration_ms, is_success)
                    VALUES (?, 'test', ?, ?, ?, ?, ?)
                ");
                $isSuccess = $httpCode >= 200 && $httpCode < 300;
                $stmt->execute([$id, json_encode($testPayload), $httpCode, $response, $duration, $isSuccess ? 1 : 0]);
                
                $msg = "Test completed: HTTP $httpCode in {$duration}ms";
                $msgType = $isSuccess ? 'success' : 'error';
            }
        }
    }
}

// Get all webhooks
$webhooks = $pdo->query("SELECT * FROM webhook_endpoints ORDER BY created_at DESC");
$webhooks = $webhooks->fetchAll(PDO::FETCH_ASSOC);

// Get recent webhook logs
$logs = $pdo->query("
    SELECT l.*, w.name as webhook_name 
    FROM webhook_event_log l 
    JOIN webhook_endpoints w ON l.webhook_id = w.id 
    ORDER BY l.created_at DESC 
    LIMIT 50
");
$logs = $logs->fetchAll(PDO::FETCH_ASSOC);

$eventTypes = ['sent', 'delivered', 'opened', 'clicked', 'bounced', 'failed', 'complained'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            color: #1a1a2e;
            line-height: 1.6;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { 
            background: linear-gradient(135deg, #0052CC 0%, #003d99 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        header h1 { font-size: 28px; margin-bottom: 8px; }
        .nav-back { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            color: white;
            text-decoration: none;
            margin-top: 15px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .card h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary { background: #0052CC; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        
        .webhook-list {
            display: grid;
            gap: 15px;
        }
        .webhook-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #0052CC;
        }
        .webhook-item.inactive {
            border-left-color: #6c757d;
            opacity: 0.7;
        }
        .webhook-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .webhook-name {
            font-weight: 600;
            font-size: 16px;
        }
        .webhook-url {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .webhook-events {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .event-badge {
            background: #0052CC;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
        }
        .webhook-stats {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #666;
        }
        
        .log-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .log-table th, .log-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
        }
        .log-table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        .status-success { color: #28a745; }
        .status-error { color: #dc3545; }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-item input {
            width: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔗 Webhook Management</h1>
            <p>Configure webhook endpoints to receive real-time event notifications.</p>
            <a href="index.php" class="nav-back">← Back to Dashboard</a>
        </header>
        
        <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>">
            <?= e($msg) ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Add Webhook Endpoint</h2>
            <form method="post">
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label>Webhook Name</label>
                    <input type="text" name="name" placeholder="My Integration" required>
                </div>
                
                <div class="form-group">
                    <label>Endpoint URL</label>
                    <input type="url" name="url" placeholder="https://api.example.com/webhook" required>
                </div>
                
                <div class="form-group">
                    <label>Events to Subscribe</label>
                    <div class="checkbox-group">
                        <?php foreach ($eventTypes as $event): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="events[]" value="<?= $event ?>">
                            <?= ucfirst($event) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="submit" name="add_webhook" class="btn btn-primary">Add Webhook</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Webhook Endpoints</h2>
            <div class="webhook-list">
                <?php foreach ($webhooks as $webhook): 
                    $events = json_decode($webhook['events'], true) ?? [];
                ?>
                <div class="webhook-item <?= $webhook['is_active'] ? '' : 'inactive' ?>">
                    <div class="webhook-header">
                        <span class="webhook-name"><?= e($webhook['name']) ?></span>
                        <div style="display:flex; gap:10px;">
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $webhook['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $webhook['is_active'] ? '0' : '1' ?>">
                                <button type="submit" name="toggle_webhook" class="btn btn-secondary" style="padding:6px 12px; font-size:12px;">
                                    <?= $webhook['is_active'] ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $webhook['id'] ?>">
                                <button type="submit" name="test_webhook" class="btn btn-success" style="padding:6px 12px; font-size:12px;">Test</button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this webhook?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $webhook['id'] ?>">
                                <button type="submit" name="delete_webhook" class="btn btn-danger" style="padding:6px 12px; font-size:12px;">Delete</button>
                            </form>
                        </div>
                    </div>
                    <div class="webhook-url"><?= e($webhook['url']) ?></div>
                    <div class="webhook-events">
                        <?php foreach ($events as $event): ?>
                        <span class="event-badge"><?= ucfirst($event) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="webhook-stats">
                        <span>Success: <?= $webhook['success_count'] ?></span>
                        <span>Failed: <?= $webhook['failure_count'] ?></span>
                        <span>Last called: <?= $webhook['last_called_at'] ? date('Y-m-d H:i', strtotime($webhook['last_called_at'])) : 'Never' ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card">
            <h2>Recent Delivery Logs</h2>
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Webhook</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['webhook_name']) ?></td>
                        <td><?= ucfirst($log['event_type']) ?></td>
                        <td class="<?= $log['is_success'] ? 'status-success' : 'status-error' ?>">
                            <?= $log['is_success'] ? '✓ Success' : '✗ Failed' ?> (<?= $log['response_code'] ?>)
                        </td>
                        <td><?= $log['duration_ms'] ?>ms</td>
                        <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
