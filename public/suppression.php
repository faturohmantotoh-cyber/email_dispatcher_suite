<?php
// suppression.php - Manage suppression list (bounces, unsubscribes, complaints)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';

ensure_dirs();
$pdo = DB::conn();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
$offset = ($page - 1) * $perPage;

// Filters
$filterType = $_GET['type'] ?? '';
$filterSearch = $_GET['search'] ?? '';

$msg = '';
$msgType = 'info';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token('suppression.php');
    rate_limit_user('suppression_manage', 50, 3600);
    
    // Add email to suppression list
    if (isset($_POST['add_suppression'])) {
        $email = trim($_POST['email'] ?? '');
        $type = $_POST['suppression_type'] ?? 'manual';
        $reason = trim($_POST['reason'] ?? '');
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Invalid email address';
            $msgType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO suppression_list (email, type, reason, source, created_by)
                    VALUES (?, ?, ?, 'manual', ?)
                    ON DUPLICATE KEY UPDATE
                    type = VALUES(type),
                    reason = VALUES(reason),
                    updated_at = NOW()
                ");
                $userId = $_SESSION['user']['id'] ?? null;
                $stmt->execute([$email, $type, $reason, $userId]);
                $msg = "Email $email added to suppression list";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Error: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
    
    // Remove from suppression list
    if (isset($_POST['remove_suppression'])) {
        // Require admin password verification
        require_admin_password('admin_password', $pdo, 'suppression.php');
        
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM suppression_list WHERE id = ?");
                $stmt->execute([$id]);
                $msg = 'Email removed from suppression list';
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Error: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
    
    // Bulk import
    if (isset($_POST['bulk_import']) && !empty($_FILES['csv_file']['tmp_name'])) {
        $type = $_POST['bulk_type'] ?? 'manual';
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $imported = 0;
        $skipped = 0;
        
        while (($data = fgetcsv($handle)) !== false) {
            $email = trim($data[0] ?? '');
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO suppression_list (email, type, source, created_by)
                        VALUES (?, ?, 'bulk_import', ?)
                    ");
                    $userId = $_SESSION['user']['id'] ?? null;
                    $stmt->execute([$email, $type, $userId]);
                    if ($stmt->rowCount() > 0) $imported++;
                    else $skipped++;
                } catch (Exception $e) {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
        }
        fclose($handle);
        $msg = "Imported $imported emails, skipped $skipped";
        $msgType = 'success';
    }
}

// Build query
$where = [];
$params = [];

if ($filterType) {
    $where[] = 'type = ?';
    $params[] = $filterType;
}

if ($filterSearch) {
    $where[] = '(email LIKE ? OR reason LIKE ?)';
    $params[] = '%' . $filterSearch . '%';
    $params[] = '%' . $filterSearch . '%';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM suppression_list $whereClause");
$countStmt->execute($params);
$totalCount = $countStmt->fetchColumn();

// Get records
$sql = "SELECT * FROM suppression_list $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = $pdo->query("
    SELECT 
        type,
        COUNT(*) as count,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_7_days
    FROM suppression_list
    GROUP BY type
")->fetchAll(PDO::FETCH_ASSOC);

$totalPages = ceil($totalCount / $perPage);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppression List Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: 700;
            color: #dc3545;
        }
        .stat-card .trend {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
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
        
        .form-inline {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-group input,
        .form-group select {
            padding: 10px 14px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary { background: #0052CC; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            color: #666;
        }
        tr:hover { background: #f8f9fa; }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-hard_bounce { background: #dc3545; color: white; }
        .badge-soft_bounce { background: #ffc107; color: #000; }
        .badge-unsubscribe { background: #6c757d; color: white; }
        .badge-spam_complaint { background: #dc3545; color: white; }
        .badge-manual { background: #0052CC; color: white; }
        
        .pagination {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
        }
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            background: white;
            border: 1px solid #ddd;
        }
        .pagination .active {
            background: #0052CC;
            color: white;
            border-color: #0052CC;
        }
        .pagination a:hover { background: #f0f0f0; }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e1e8ed;
        }
        .tabs a {
            padding: 12px 20px;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        .tabs a.active {
            color: #0052CC;
            border-bottom-color: #0052CC;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🚫 Suppression List</h1>
            <p>Manage bounced emails, unsubscribes, and spam complaints to protect your sender reputation.</p>
            <a href="index.php" class="nav-back">← Back to Dashboard</a>
        </header>
        
        <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>">
            <?= e($msg) ?>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <?php foreach ($stats as $stat): ?>
            <div class="stat-card">
                <h3><?= ucwords(str_replace('_', ' ', $stat['type'])) ?></h3>
                <div class="number"><?= number_format($stat['count']) ?></div>
                <div class="trend">+<?= $stat['last_7_days'] ?> in last 7 days</div>
            </div>
            <?php endforeach; ?>
            <div class="stat-card">
                <h3>Total Suppressed</h3>
                <div class="number"><?= number_format($totalCount) ?></div>
                <div class="trend">All time</div>
            </div>
        </div>
        
        <div class="card">
            <div class="tabs">
                <a href="?" class="<?= !isset($_GET['action']) ? 'active' : '' ?>">List</a>
                <a href="?action=add" class="<?= ($_GET['action'] ?? '') === 'add' ? 'active' : '' ?>">Add Email</a>
                <a href="?action=import" class="<?= ($_GET['action'] ?? '') === 'import' ? 'active' : '' ?>">Bulk Import</a>
            </div>
            
            <?php if (($_GET['action'] ?? '') === 'add'): ?>
            <!-- Add Single Email -->
            <h2>Add Email to Suppression List</h2>
            <form method="post">
                <?= csrf_field() ?>
                <div class="form-inline">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required placeholder="user@example.com">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="suppression_type">
                            <option value="manual">Manual</option>
                            <option value="hard_bounce">Hard Bounce</option>
                            <option value="soft_bounce">Soft Bounce</option>
                            <option value="spam_complaint">Spam Complaint</option>
                            <option value="unsubscribe">Unsubscribe</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reason (optional)</label>
                        <input type="text" name="reason" placeholder="Why is this email suppressed?">
                    </div>
                </div>
                <button type="submit" name="add_suppression" class="btn btn-danger">Add to Suppression List</button>
            </form>
            
            <?php elseif (($_GET['action'] ?? '') === 'import'): ?>
            <!-- Bulk Import -->
            <h2>Bulk Import from CSV</h2>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="form-inline">
                    <div class="form-group">
                        <label>CSV File (one email per row)</label>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </div>
                    <div class="form-group">
                        <label>Import as Type</label>
                        <select name="bulk_type">
                            <option value="manual">Manual</option>
                            <option value="hard_bounce">Hard Bounce</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="bulk_import" class="btn btn-primary">Import CSV</button>
            </form>
            
            <?php else: ?>
            <!-- List with Filters -->
            <form method="get" class="form-inline" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label>Filter by Type</label>
                    <select name="type" onChange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="hard_bounce" <?= $filterType === 'hard_bounce' ? 'selected' : '' ?>>Hard Bounce</option>
                        <option value="soft_bounce" <?= $filterType === 'soft_bounce' ? 'selected' : '' ?>>Soft Bounce</option>
                        <option value="spam_complaint" <?= $filterType === 'spam_complaint' ? 'selected' : '' ?>>Spam Complaint</option>
                        <option value="unsubscribe" <?= $filterType === 'unsubscribe' ? 'selected' : '' ?>>Unsubscribe</option>
                        <option value="manual" <?= $filterType === 'manual' ? 'selected' : '' ?>>Manual</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" value="<?= e($filterSearch) ?>" placeholder="Email or reason...">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <a href="?" class="btn btn-secondary" style="text-decoration:none;">Clear</a>
            </form>
            
            <?php if (empty($records)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
                <h3>No suppressed emails found</h3>
                <p>Your suppression list is empty. Emails that bounce or users who unsubscribe will appear here.</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Source</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= e($record['email']) ?></td>
                        <td>
                            <span class="badge badge-<?= $record['type'] ?>">
                                <?= str_replace('_', ' ', $record['type']) ?>
                            </span>
                        </td>
                        <td><?= e($record['reason'] ?? '—') ?></td>
                        <td><?= e($record['source'] ?? '—') ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($record['created_at'])) ?></td>
                        <td>
                            <button type="button" onclick="confirmRemoveSuppression(<?= $record['id'] ?>, '<?= e($record['email']) ?>')" class="btn btn-secondary" style="padding:6px 12px; font-size:12px;">
                                Remove
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&type=<?= e($filterType) ?>&search=<?= e($filterSearch) ?>">← Prev</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                <?php if ($i == $page): ?>
                <span class="active"><?= $i ?></span>
                <?php else: ?>
                <a href="?page=<?= $i ?>&type=<?= e($filterType) ?>&search=<?= e($filterSearch) ?>"><?= $i ?></a>
                <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&type=<?= e($filterType) ?>&search=<?= e($filterSearch) ?>">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
async function confirmRemoveSuppression(id, email) {
  const result = await Swal.fire({
    title: 'Remove from Suppression List?',
    html: '<p style="color: #666; font-size: 14px;">Anda akan menghapus <strong>' + email + '</strong> dari daftar suppression.</p><p style="color: #dc3545; font-weight: bold;">⚠️ Email ini akan dapat menerima email lagi!</p>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Batal',
    cancelButtonColor: '#6b7280',
    allowOutsideClick: false,
    allowEscapeKey: false
  });
  
  if (result.isConfirmed) {
    // Prompt for admin password
    const { value: password } = await Swal.fire({
      title: 'Konfirmasi Password Administrator',
      input: 'password',
      inputLabel: 'Masukkan password Anda untuk melanjutkan:',
      inputPlaceholder: 'Password',
      inputAttributes: {
        maxlength: 50,
        autocapitalize: 'off',
        autocorrect: 'off'
      },
      showCancelButton: true,
      confirmButtonText: 'Konfirmasi',
      confirmButtonColor: '#dc2626',
      cancelButtonText: 'Batal',
      cancelButtonColor: '#6b7280',
      allowOutsideClick: false,
      allowEscapeKey: false,
      inputValidator: (value) => {
        if (!value) {
          return 'Password wajib diisi!'
        }
      }
    });
    
    if (password) {
      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'suppression.php';
      
      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = 'csrf_token';
      csrfInput.value = '<?= $csrf ?>';
      
      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'id';
      idInput.value = id;
      
      const removeInput = document.createElement('input');
      removeInput.type = 'hidden';
      removeInput.name = 'remove_suppression';
      removeInput.value = '1';
      
      const passwordInput = document.createElement('input');
      passwordInput.type = 'hidden';
      passwordInput.name = 'admin_password';
      passwordInput.value = password;
      
      form.appendChild(csrfInput);
      form.appendChild(idInput);
      form.appendChild(removeInput);
      form.appendChild(passwordInput);
      document.body.appendChild(form);
      form.submit();
    }
  }
}
</script>
</html>
