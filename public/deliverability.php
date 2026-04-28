<?php
// deliverability.php - DKIM/SPF/DMARC Configuration & Email Deliverability Settings
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';

ensure_dirs();
$pdo = DB::conn();

// Require admin role
requireRole(['admin'], 'index.php');

$msg = '';
$msgType = 'info';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token('index.php');
    
    // Rate limit admin actions
    rate_limit_user('deliverability_config', 30, 300);
    
    if (isset($_POST['save_dkim'])) {
        $domain = trim($_POST['dkim_domain'] ?? '');
        $selector = trim($_POST['dkim_selector'] ?? 'default');
        $privateKey = trim($_POST['dkim_private_key'] ?? '');
        $publicKey = trim($_POST['dkim_public_key'] ?? '');
        
        if ($domain && $selector) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO email_deliverability_config 
                    (domain, dkim_enabled, dkim_selector, dkim_private_key, dkim_public_key)
                    VALUES (?, 1, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    dkim_enabled = 1,
                    dkim_selector = VALUES(dkim_selector),
                    dkim_private_key = VALUES(dkim_private_key),
                    dkim_public_key = VALUES(dkim_public_key)
                ");
                $stmt->execute([$domain, $selector, $privateKey, $publicKey]);
                $msg = 'DKIM configuration saved successfully!';
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Error saving DKIM: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
    
    if (isset($_POST['save_spf'])) {
        $domain = trim($_POST['spf_domain'] ?? '');
        $spfRecord = trim($_POST['spf_record'] ?? '');
        
        if ($domain && $spfRecord) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO email_deliverability_config 
                    (domain, spf_record)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE
                    spf_record = VALUES(spf_record)
                ");
                $stmt->execute([$domain, $spfRecord]);
                $msg = 'SPF record saved successfully!';
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Error saving SPF: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
    
    if (isset($_POST['save_dmarc'])) {
        $domain = trim($_POST['dmarc_domain'] ?? '');
        $policy = $_POST['dmarc_policy'] ?? 'none';
        $percentage = intval($_POST['dmarc_percentage'] ?? 100);
        $rua = trim($_POST['dmarc_rua'] ?? '');
        $ruf = trim($_POST['dmarc_ruf'] ?? '');
        
        if ($domain) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO email_deliverability_config 
                    (domain, dmarc_policy, dmarc_percentage, dmarc_rua, dmarc_ruf)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    dmarc_policy = VALUES(dmarc_policy),
                    dmarc_percentage = VALUES(dmarc_percentage),
                    dmarc_rua = VALUES(dmarc_rua),
                    dmarc_ruf = VALUES(dmarc_ruf)
                ");
                $stmt->execute([$domain, $policy, $percentage, $rua, $ruf]);
                $msg = 'DMARC policy saved successfully!';
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Error saving DMARC: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
    
    if (isset($_POST['toggle_setting'])) {
        $key = $_POST['setting_key'] ?? '';
        $value = $_POST['setting_value'] ?? '0';
        
        if ($key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (`key`, `value`, `type`)
                    VALUES (?, ?, 'boolean')
                    ON DUPLICATE KEY UPDATE
                    `value` = VALUES(`value`)
                ");
                $stmt->execute([$key, $value]);
                $msg = 'Setting updated successfully!';
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Error updating setting: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
}

// Fetch current configurations
$configs = $pdo->query("SELECT * FROM email_deliverability_config ORDER BY domain");
$configs = $configs->fetchAll(PDO::FETCH_ASSOC);

// Fetch system settings
$settingsStmt = $pdo->query("SELECT * FROM system_settings WHERE `key` IN (
    'csrf_enabled', 'rate_limit_enabled', 'bounce_handling_enabled',
    'open_tracking_enabled', 'click_tracking_enabled', 'dkim_signing_enabled',
    'max_emails_per_hour', 'max_emails_per_day', 'webhook_retries', 'webhook_timeout'
)");
$settings = [];
while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key']] = $row['value'];
}

// Default values if not set
$defaults = [
    'csrf_enabled' => '1',
    'rate_limit_enabled' => '1',
    'bounce_handling_enabled' => '1',
    'open_tracking_enabled' => '1',
    'click_tracking_enabled' => '1',
    'dkim_signing_enabled' => '0',
    'max_emails_per_hour' => '100',
    'max_emails_per_day' => '1000',
    'webhook_retries' => '3',
    'webhook_timeout' => '5000'
];
foreach ($defaults as $key => $val) {
    if (!isset($settings[$key])) {
        $settings[$key] = $val;
    }
}

// Generate DKIM key pair helper
function generateDKIMKeys() {
    $config = [
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 1024
    ];
    $res = openssl_pkey_new($config);
    openssl_pkey_export($res, $privateKey);
    $publicKey = openssl_pkey_get_details($res);
    $publicKey = $publicKey['key'];
    
    // Format public key for DNS TXT record
    $publicKey = str_replace(["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n", "\r"], '', $publicKey);
    
    return [
        'private' => $privateKey,
        'public' => $publicKey
    ];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Deliverability & Security Settings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
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
        header p { opacity: 0.9; }
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
            transition: background 0.2s;
        }
        .nav-back:hover { background: rgba(255,255,255,0.3); }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .card h2 {
            font-size: 20px;
            color: #0052CC;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card h2 span { font-size: 24px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="email"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0052CC;
        }
        .form-group textarea {
            font-family: 'Courier New', monospace;
            min-height: 120px;
            resize: vertical;
        }
        .form-group small {
            display: block;
            margin-top: 6px;
            color: #666;
            font-size: 12px;
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
            transition: all 0.2s;
        }
        .btn-primary {
            background: #0052CC;
            color: white;
        }
        .btn-primary:hover { background: #003d99; }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .btn-secondary:hover { background: #e0e0e0; }
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #cce5ff; color: #004085; border: 1px solid #b3d7ff; }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
        }
        
        .dns-record {
            background: #f8f9fa;
            border-left: 4px solid #0052CC;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
        }
        .dns-record strong {
            display: block;
            margin-bottom: 10px;
            color: #0052CC;
            font-family: 'Inter', sans-serif;
        }
        .dns-record code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .toggle-switch input[type="checkbox"] {
            width: 50px;
            height: 26px;
            -webkit-appearance: none;
            appearance: none;
            background: #ccc;
            border-radius: 13px;
            position: relative;
            cursor: pointer;
            transition: background 0.3s;
        }
        .toggle-switch input[type="checkbox"]:checked {
            background: #28a745;
        }
        .toggle-switch input[type="checkbox"]::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s;
        }
        .toggle-switch input[type="checkbox"]:checked::after {
            transform: translateX(24px);
        }
        .toggle-switch label {
            flex: 1;
            cursor: pointer;
        }
        .toggle-switch .desc {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .info-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .info-box h4 {
            color: #1565c0;
            margin-bottom: 10px;
        }
        .info-box ul {
            margin-left: 20px;
            color: #333;
        }
        .info-box li { margin-bottom: 6px; }
        
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .container { padding: 15px; }
            header { padding: 20px; }
            .card { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Email Deliverability & Security</h1>
            <p>Configure DKIM, SPF, DMARC, and security settings to improve email delivery and protect your domain reputation.</p>
            <a href="index.php" class="nav-back">← Back to Dashboard</a>
        </header>
        
        <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>">
            <span style="font-size: 20px;"><?= $msgType === 'success' ? '✓' : ($msgType === 'error' ? '✗' : 'ℹ') ?></span>
            <?= e($msg) ?>
        </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h4>🔒 Why These Settings Matter</h4>
            <ul>
                <li><strong>DKIM</strong> - Digitally signs emails to prove they weren't modified in transit</li>
                <li><strong>SPF</strong> - Specifies which servers are allowed to send email for your domain</li>
                <li><strong>DMARC</strong> - Tells email providers what to do with suspicious emails and sends you reports</li>
                <li><strong>Rate Limiting</strong> - Prevents abuse and protects your sending reputation</li>
                <li><strong>Bounce Handling</strong> - Automatically removes bad addresses to maintain list hygiene</li>
            </ul>
        </div>
        
        <div class="grid-2">
            <!-- Security Settings -->
            <div class="card">
                <h2><span>🛡️</span> Security Settings</h2>
                
                <form method="post">
                    <?= csrf_field() ?>
                    
                    <div class="toggle-switch">
                        <input type="hidden" name="setting_key" value="csrf_enabled">
                        <input type="checkbox" name="setting_value" value="1" id="csrf_enabled" 
                            <?= $settings['csrf_enabled'] === '1' ? 'checked' : '' ?>
                            onChange="this.form.submit()">
                        <label for="csrf_enabled">
                            <strong>CSRF Protection</strong>
                            <div class="desc">Prevents cross-site request forgery attacks on all forms</div>
                        </label>
                        <input type="hidden" name="toggle_setting" value="1">
                    </div>
                </form>
                
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="toggle-switch">
                        <input type="hidden" name="setting_key" value="rate_limit_enabled">
                        <input type="checkbox" name="setting_value" value="1" id="rate_limit_enabled" 
                            <?= $settings['rate_limit_enabled'] === '1' ? 'checked' : '' ?>
                            onChange="this.form.submit()">
                        <label for="rate_limit_enabled">
                            <strong>Rate Limiting</strong>
                            <div class="desc">Limits requests per user to prevent abuse</div>
                        </label>
                        <input type="hidden" name="toggle_setting" value="1">
                    </div>
                </form>
                
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="toggle-switch">
                        <input type="hidden" name="setting_key" value="bounce_handling_enabled">
                        <input type="checkbox" name="setting_value" value="1" id="bounce_handling_enabled" 
                            <?= $settings['bounce_handling_enabled'] === '1' ? 'checked' : '' ?>
                            onChange="this.form.submit()">
                        <label for="bounce_handling_enabled">
                            <strong>Automatic Bounce Handling</strong>
                            <div class="desc">Auto-suppress emails that hard bounce</div>
                        </label>
                        <input type="hidden" name="toggle_setting" value="1">
                    </div>
                </form>
            </div>
            
            <!-- Rate Limits -->
            <div class="card">
                <h2><span>⏱️</span> Rate Limits</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="toggle_setting" value="1">
                    <input type="hidden" name="setting_key" value="max_emails_per_hour">
                    
                    <div class="form-group">
                        <label>Max Emails Per Hour</label>
                        <input type="number" name="setting_value" value="<?= e($settings['max_emails_per_hour']) ?>" 
                            min="1" max="10000" onChange="this.form.submit()">
                        <small>Maximum emails a user can send per hour</small>
                    </div>
                </form>
                
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="toggle_setting" value="1">
                    <input type="hidden" name="setting_key" value="max_emails_per_day">
                    
                    <div class="form-group">
                        <label>Max Emails Per Day</label>
                        <input type="number" name="setting_value" value="<?= e($settings['max_emails_per_day']) ?>" 
                            min="1" max="100000" onChange="this.form.submit()">
                        <small>Maximum emails a user can send per day</small>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="grid-2">
            <!-- DKIM Configuration -->
            <div class="card">
                <h2><span>🔐</span> DKIM Configuration</h2>
                <p style="margin-bottom: 20px; color: #666;">DKIM adds a digital signature to your emails to verify they haven't been tampered with.</p>
                
                <form method="post">
                    <?= csrf_field() ?>
                    
                    <div class="form-group">
                        <label>Domain</label>
                        <input type="text" name="dkim_domain" placeholder="example.com" required>
                        <small>The domain you send email from</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Selector</label>
                        <input type="text" name="dkim_selector" value="default" required>
                        <small>Usually "default" or "mail" - part of the DNS record name</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Private Key (for signing emails)</label>
                        <textarea name="dkim_private_key" placeholder="-----BEGIN RSA PRIVATE KEY-----
...
-----END RSA PRIVATE KEY-----"></textarea>
                        <small>Keep this secret! Used to sign outgoing emails.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Public Key (for DNS)</label>
                        <textarea name="dkim_public_key" placeholder="-----BEGIN PUBLIC KEY-----
...
-----END PUBLIC KEY-----"></textarea>
                        <small>Add this to your DNS as a TXT record</small>
                    </div>
                    
                    <button type="submit" name="save_dkim" class="btn btn-primary">Save DKIM Configuration</button>
                </form>
                
                <?php foreach ($configs as $config): ?>
                <?php if ($config['dkim_enabled']): ?>
                <div class="dns-record">
                    <strong>DNS TXT Record for <?= e($config['domain']) ?>:</strong>
                    <p>Name: <code><?= e($config['dkim_selector']) ?>._domainkey.<?= e($config['domain']) ?></code></p>
                    <p>Value: <code>v=DKIM1; k=rsa; p=<?= substr(e($config['dkim_public_key']), 0, 50) ?>...</code></p>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- SPF Record -->
            <div class="card">
                <h2><span>📋</span> SPF Configuration</h2>
                <p style="margin-bottom: 20px; color: #666;">SPF tells email providers which servers are authorized to send email for your domain.</p>
                
                <form method="post">
                    <?= csrf_field() ?>
                    
                    <div class="form-group">
                        <label>Domain</label>
                        <input type="text" name="spf_domain" placeholder="example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label>SPF Record</label>
                        <input type="text" name="spf_record" value="v=spf1 include:_spf.google.com ~all">
                        <small>Common: v=spf1 mx include:_spf.google.com ~all</small>
                    </div>
                    
                    <button type="submit" name="save_spf" class="btn btn-primary">Save SPF Record</button>
                </form>
                
                <div class="dns-record">
                    <strong>DNS TXT Record:</strong>
                    <p>Name: <code>@</code> (or your domain)</p>
                    <p>Value: <code>v=spf1 mx include:_spf.google.com ~all</code></p>
                    <p style="margin-top: 10px; color: #666;">
                        <strong>Mechanisms:</strong><br>
                        mx = allow mail servers<br>
                        include = allow third-party<br>
                        ~all = soft fail (mark as suspicious)<br>
                        -all = hard fail (reject)
                    </p>
                </div>
            </div>
        </div>
        
        <!-- DMARC Configuration -->
        <div class="card">
            <h2><span>🛡️</span> DMARC Policy</h2>
            <p style="margin-bottom: 20px; color: #666;">DMARC tells email providers what to do with emails that fail SPF or DKIM checks, and sends you reports.</p>
            
            <form method="post">
                <?= csrf_field() ?>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label>Domain</label>
                        <input type="text" name="dmarc_domain" placeholder="example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Policy</label>
                        <select name="dmarc_policy">
                            <option value="none">None (monitor only)</option>
                            <option value="quarantine">Quarantine (send to spam)</option>
                            <option value="reject">Reject (bounce email)</option>
                        </select>
                        <small>Start with "None" to collect reports, then move to "Quarantine" or "Reject"</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Percentage</label>
                        <input type="number" name="dmarc_percentage" value="100" min="0" max="100">
                        <small>Apply policy to this % of emails (for gradual rollout)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Report Email (RUA - Aggregate Reports)</label>
                        <input type="email" name="dmarc_rua" placeholder="dmarc-reports@example.com">
                        <small>Where to send daily summary reports</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Forensic Email (RUF - Detailed Reports)</label>
                        <input type="email" name="dmarc_ruf" placeholder="dmarc-forensic@example.com">
                        <small>Where to send detailed failure reports (optional)</small>
                    </div>
                </div>
                
                <button type="submit" name="save_dmarc" class="btn btn-primary">Save DMARC Policy</button>
            </form>
            
            <div class="dns-record">
                <strong>DNS TXT Record:</strong>
                <p>Name: <code>_dmarc</code></p>
                <p>Value: <code>v=DMARC1; p=none; rua=mailto:dmarc-reports@example.com; pct=100</code></p>
            </div>
        </div>
        
        <!-- Tracking Settings -->
        <div class="card">
            <h2><span>📊</span> Email Tracking</h2>
            <div class="grid-2">
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="toggle-switch">
                        <input type="hidden" name="setting_key" value="open_tracking_enabled">
                        <input type="checkbox" name="setting_value" value="1" id="open_tracking_enabled" 
                            <?= $settings['open_tracking_enabled'] === '1' ? 'checked' : '' ?>
                            onChange="this.form.submit()">
                        <label for="open_tracking_enabled">
                            <strong>Open Tracking</strong>
                            <div class="desc">Track when recipients open emails (adds 1x1 pixel)</div>
                        </label>
                        <input type="hidden" name="toggle_setting" value="1">
                    </div>
                </form>
                
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="toggle-switch">
                        <input type="hidden" name="setting_key" value="click_tracking_enabled">
                        <input type="checkbox" name="setting_value" value="1" id="click_tracking_enabled" 
                            <?= $settings['click_tracking_enabled'] === '1' ? 'checked' : '' ?>
                            onChange="this.form.submit()">
                        <label for="click_tracking_enabled">
                            <strong>Click Tracking</strong>
                            <div class="desc">Track when recipients click links (rewrites URLs)</div>
                        </label>
                        <input type="hidden" name="toggle_setting" value="1">
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Webhook Settings -->
        <div class="card">
            <h2><span>🔗</span> Webhook Configuration</h2>
            <div class="grid-2">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="toggle_setting" value="1">
                    <input type="hidden" name="setting_key" value="webhook_retries">
                    
                    <div class="form-group">
                        <label>Retry Attempts</label>
                        <input type="number" name="setting_value" value="<?= e($settings['webhook_retries']) ?>" 
                            min="0" max="10" onChange="this.form.submit()">
                        <small>How many times to retry failed webhook calls</small>
                    </div>
                </form>
                
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="toggle_setting" value="1">
                    <input type="hidden" name="setting_key" value="webhook_timeout">
                    
                    <div class="form-group">
                        <label>Timeout (milliseconds)</label>
                        <input type="number" name="setting_value" value="<?= e($settings['webhook_timeout']) ?>" 
                            min="1000" max="30000" step="500" onChange="this.form.submit()">
                        <small>How long to wait for webhook response</small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
