<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';

$pdo = DB::conn();

// Helper for PHP < 8.0
if (!function_exists('array_any')) {
    function array_any(array $array, callable $callback): bool {
        foreach ($array as $item) {
            if ($callback($item)) return true;
        }
        return false;
    }
}

/**
 * Check if a result JSON file exists for a job and update database if needed
 */
function update_job_from_result(PDO $pdo, int $jobId) {
    $resultPath = TEMP_DIR . DIRECTORY_SEPARATOR . 'result_job_' . $jobId . '.json';
    if (!file_exists($resultPath)) {
        return; // Still processing
    }
    
    $resText = file_get_contents($resultPath);
    $res = json_decode($resText, true);
    if (!is_array($res) || empty($res)) {
        return;
    }
    
    // Update each item status
    $upd = $pdo->prepare("UPDATE mail_job_items SET status=?, status_message=?, sent_at=IF(?='sent', NOW(), NULL) WHERE id=?");
    $sent = 0;
    $failed = 0;
    
    foreach ($res as $row) {
        $status = $row['status'] ?? 'failed';
        $msg = $row['message'] ?? '';
        $id = (int)($row['id'] ?? 0);
        $relatedIds = $row['related_ids'] ?? [];
        
        // For group items, update all related IDs
        if (!empty($relatedIds) && is_array($relatedIds)) {
            foreach ($relatedIds as $rid) {
                $rid = (int)$rid;
                if ($rid > 0) {
                    $upd->execute([$status, $msg, $status, $rid]);
                    if ($status === 'sent') $sent++;
                    elseif ($status === 'failed') $failed++;
                }
            }
        } elseif ($id > 0) {
            $upd->execute([$status, $msg, $status, $id]);
            if ($status === 'sent') {
                $sent++;
            } elseif ($status === 'failed') {
                $failed++;
            }
        }
    }
    
    // Update job status
    $statusFinal = ($failed > 0 && $sent == 0) ? 'failed' : 'completed';
    $pdo->prepare("UPDATE mail_jobs SET status=? WHERE id=?")->execute([$statusFinal, $jobId]);
}

// Fetch all jobs with their status summary
$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) { die('User tidak terautentikasi'); }

$jobs = $pdo->prepare("
    SELECT 
        j.id,
        j.created_at,
        j.subject,
        j.status,
        j.cc,
        COUNT(i.id) as total_items,
        SUM(CASE WHEN i.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN i.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN i.status = 'skipped' THEN 1 ELSE 0 END) as skipped_count
    FROM mail_jobs j
    LEFT JOIN mail_job_items i ON i.mail_job_id = j.id
    WHERE j.created_by = ?
    GROUP BY j.id, j.created_at, j.subject, j.status, j.cc
    ORDER BY j.created_at DESC
");
$jobs->execute([$userId]);
$jobs = $jobs->fetchAll(PDO::FETCH_ASSOC);

// Try to update "processing" jobs from result files
foreach ($jobs as $job) {
    if ($job['status'] === 'processing') {
        update_job_from_result($pdo, (int)$job['id']);
    }
}

// Re-fetch jobs after updates
$stmt = $pdo->prepare("
    SELECT 
        j.id,
        j.created_at,
        j.subject,
        j.status,
        j.cc,
        COUNT(i.id) as total_items,
        SUM(CASE WHEN i.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN i.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN i.status = 'skipped' THEN 1 ELSE 0 END) as skipped_count
    FROM mail_jobs j
    LEFT JOIN mail_job_items i ON i.mail_job_id = j.id
    WHERE j.created_by = ?
    GROUP BY j.id, j.created_at, j.subject, j.status, j.cc
    ORDER BY j.created_at DESC
");
$stmt->execute([$userId]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get detail for specific job (if requested)
$selectedJobId = isset($_GET['job']) ? intval($_GET['job']) : null;
$jobDetails = [];
if ($selectedJobId) {
    $stmt = $pdo->prepare("
        SELECT 
            j.id,
            j.created_at,
            j.subject,
            j.body,
            j.status,
            j.cc,
            j.mode
        FROM mail_jobs j
        WHERE j.id = ?
    ");
    $stmt->execute([$selectedJobId]);
    $jobDetails['info'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($jobDetails['info']) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM mail_job_items
            WHERE mail_job_id = ?
            ORDER BY recipient_email
        ");
        $stmt->execute([$selectedJobId]);
        $jobDetails['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Email Logs</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php if (!$selectedJobId): ?>
  <!-- Auto-refresh disabled to prevent blinking -->
<?php else: ?>
  <!-- Auto-refresh disabled to prevent blinking -->
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/custom.css?v=3.0">
</head>
<body>
<header>
  <h2 style="margin:0;">📋 Email Logs</h2>
  <div style="margin-top:8px;">
    <a class="btn secondary" href="index.php">⟵ Kembali ke Dashboard</a>
  </div>
</header>

<main>
  <?php if (empty($jobs)): ?>
    <div class="card">
      <div class="body">
        <p class="small"><em>Belum ada email yang dikirim.</em></p>
      </div>
    </div>
  <?php else: ?>
    
    <?php if ($selectedJobId && $jobDetails['info']): ?>
      <!-- Detail View -->
      <div class="card">
        <h3>Detail Job #<?= e($jobDetails['info']['id']) ?></h3>
        <div class="body">
          <div class="grid-2">
            <div>
              <p style="color:black;"><strong>Subject:</strong><br><?= e($jobDetails['info']['subject']) ?></p>
              <p style="color:black;"><strong>Status:</strong><br><span class="badge <?= strtolower($jobDetails['info']['status']) ?>"><?= e($jobDetails['info']['status']) ?></span></p>
              <p style="color:black;"><strong>Mode:</strong><br><?= e($jobDetails['info']['mode']) ?></p>
            </div>
            <div>
              <p style="color:black;"><strong>Waktu dibuat:</strong><br><?= e($jobDetails['info']['created_at']) ?></p>
              <p style="color:black;"><strong>CC:</strong><br><?= e($jobDetails['info']['cc'] ?: '—') ?></p>
            </div>
          </div>
          <p style="color:black;"><strong>Body:</strong></p>
          <iframe sandbox="" srcdoc="<?= htmlspecialchars('<html><body style=&quot;font-family:Segoe UI,Arial,sans-serif;font-size:14px;line-height:1.6;color:#222;margin:0;padding:10px;&quot;>' . $jobDetails['info']['body'] . '</body></html>', ENT_QUOTES, 'UTF-8') ?>" style="width:100%;min-height:200px;max-height:400px;border:1px solid #E3F2FD;border-radius:6px;background:#fff;"></iframe>
        </div>
      </div>

      <!-- Items Detail -->
      <div class="card">
        <h3>Detail Pengiriman (<?= count($jobDetails['items']) ?> item)</h3>
        <div class="body">
          <table class="table">
            <thead>
              <tr>
                <th>Penerima</th>
                <th>Email</th>
                <th>Status</th>
                <th>Score</th>
                <th>Lampiran</th>
                <th>Waktu Kirim</th>
                <th>Pesan</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($jobDetails['items'] as $item): ?>
                <tr>
                  <td><?= e($item['recipient_name'] ?: '—') ?></td>
                  <td><?= e($item['recipient_email']) ?></td>
                  <td><span class="badge <?= strtolower($item['status']) ?>"><?= e($item['status']) ?></span></td>
                  <td><?= isset($item['similarity_score']) ? round($item['similarity_score'], 2) : '—' ?></td>
                  <td><?= e(basename($item['attachment_path'] ?? '')) ?: '—' ?></td>
                  <td><?= e($item['sent_at'] ?: '—') ?></td>
                  <td title="<?= e($item['status_message'] ?: '') ?>"><span class="small"><?= e(substr($item['status_message'] ?? '', 0, 40)) ?><?= strlen($item['status_message'] ?? '') > 40 ? '...' : '' ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <p style="text-align:center;">
        <a class="btn secondary" href="logs.php">⟵ Kembali ke Daftar Jobs</a>
      </p>

    <?php else: ?>
      <!-- List View -->
      <div class="card">
        <h3>Daftar Email Jobs</h3>
        <div class="body">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Waktu Dibuat</th>
                <th>Subject</th>
                <th>Total</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($jobs as $job): ?>
                <tr>
                  <td>#<?= (int)$job['id'] ?></td>
                  <td><?= e($job['created_at']) ?></td>
                  <td><strong><?= e(substr($job['subject'], 0, 50)) ?></strong><?= strlen($job['subject']) > 50 ? '...' : '' ?></td>
                  <td>
                    <div style="font-size:13px;line-height:1.6;">
                      Total: <strong><?= (int)$job['total_items'] ?></strong><br>
                      ✅ Sent: <span style="color:#16a34a;font-weight:600;"><?= (int)($job['sent_count'] ?? 0) ?></span>
                      ❌ Failed: <span style="color:#dc2626;font-weight:600;"><?= (int)($job['failed_count'] ?? 0) ?></span>
                      ⏳ Pending: <span style="color:#f59e0b;font-weight:600;"><?= (int)($job['pending_count'] ?? 0) ?></span>
                      ⊘ Skipped: <span style="color:#6b7280;font-weight:600;"><?= (int)($job['skipped_count'] ?? 0) ?></span>
                    </div>
                  </td>
                  <td>
                    <span class="badge <?= strtolower($job['status']) ?>"><?= e($job['status']) ?></span>
                  </td>
                  <td>
                    <a class="btn" href="logs.php?job=<?= (int)$job['id'] ?>">Lihat Detail →</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</main>

<style>
/* Bold and readable fonts throughout */
body { font-weight: 500; }
h1, h2, h3, h4, h5, h6 { font-weight: 700; }
p, span, td, th, a { font-weight: 600; }
strong, .strong { font-weight: 700; }
.btn { font-weight: 600; }
.badge { font-weight: 600; }
table { font-weight: 600; }
.table thead th { font-weight: 700; }
.card h3 { font-weight: 700; }
.small { font-weight: 500; }

/* Page Transition Animations */
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeOut {
  from { opacity: 1; transform: translateY(0); }
  to { opacity: 0; transform: translateY(-20px); }
}
@keyframes overlayIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.page-transition {
  position: fixed;
  inset: 0;
  background: linear-gradient(135deg, #0052CC 0%, #003fa3 100%);
  z-index: 9999;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.page-transition.active {
  opacity: 1;
  pointer-events: auto;
  animation: overlayIn 0.3s ease-out;
}

main { animation: fadeInUp 0.6s ease-out 0.1s both; }
main.transitioning { animation: fadeOut 0.3s ease-out forwards; }
</style>

<script>
// Page Transition Animations
function initPageTransitions() {
  const navLinks = document.querySelectorAll('a[href$=".php"]');
  navLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      if (!href || href.startsWith('#') || href.startsWith('http')) return;
      e.preventDefault();
      const overlay = document.getElementById('pageTransition');
      const mainContent = document.querySelector('main');
      
      if (overlay) {
        overlay.classList.add('active');
        if (mainContent) mainContent.classList.add('transitioning');
        setTimeout(() => { window.location.href = href; }, 300);
      } else {
        window.location.href = href;
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', initPageTransitions);
window.addEventListener('load', function() {
  const overlay = document.getElementById('pageTransition');
  const mainContent = document.querySelector('main');
  if (overlay) {
    overlay.classList.remove('active');
    if (mainContent) mainContent.classList.remove('transitioning');
  }
  
  // Start auto-refresh for logs page (only in list view, not detail view)
  if (!new URLSearchParams(window.location.search).has('job')) {
    initAutoRefresh();
  }
});

// Auto-refresh logs page when there are processing jobs
let autoRefreshInterval = null;

function initAutoRefresh() {
  // Check if there are any "processing" badges
  const hasProcessing = Array.from(document.querySelectorAll('.badge')).some(
    badge => badge.textContent.toLowerCase() === 'processing'
  );
  
  if (hasProcessing) {
    // Refresh every 2 seconds if processing
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(() => {
      fetch(window.location.href)
        .then(r => r.text())
        .then(html => {
          const parser = new DOMParser();
          const newDoc = parser.parseFromString(html, 'text/html');
          const newContent = newDoc.querySelector('main');
          const oldContent = document.querySelector('main');
          
          if (newContent && oldContent) {
            oldContent.innerHTML = newContent.innerHTML;
            
            // Check if still processing
            const stillProcessing = Array.from(oldContent.querySelectorAll('.badge')).some(
              badge => badge.textContent.toLowerCase() === 'processing'
            );
            
            if (!stillProcessing) {
              clearInterval(autoRefreshInterval);
            }
          }
        })
        .catch(err => console.warn('Auto-refresh failed:', err));
    }, 2000);  // Refresh every 2 seconds
  }
}
</script>

<!-- AI Assistant Widget -->
<script src="../assets/js/ai-assistant.js?v=1.0"></script>

<!-- Page Transition Overlay -->
<div class="page-transition" id="pageTransition"></div>

</body>
</html>
