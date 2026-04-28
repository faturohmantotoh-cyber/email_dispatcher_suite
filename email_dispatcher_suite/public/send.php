<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';
ensure_dirs();
$pdo = DB::conn();

$subject = $_POST['subject'] ?? '';
$cc = $_POST['cc'] ?? '';
$body = $_POST['body'] ?? '';
$items = $_POST['items'] ?? [];

if (!$subject || !$items) { die('Input tidak lengkap'); }

/**
 * Add inline border styles to <table>, <td>, <th> tags that lack them.
 * Email clients ignore <style> blocks, so borders must be inline.
 */
function add_table_borders(string $html): string {
    if (strpos($html, '<table') === false) return $html;

    $tableBorder = 'border-collapse:collapse;border:1px solid #999;width:100%;';
    $cellBorder  = 'border:1px solid #999;padding:6px 8px;';

    // Add styles to <table> tags
    $html = preg_replace_callback('/<table([^>]*)>/i', function($m) use ($tableBorder) {
        $attrs = $m[1];
        if (stripos($attrs, 'style=') !== false) {
            // Append to existing style if border not already present
            if (stripos($attrs, 'border') === false) {
                $attrs = preg_replace('/style\s*=\s*["\']([^"\']*)["\']/', 'style="$1;' . $tableBorder . '"', $attrs);
            }
        } else {
            $attrs .= ' style="' . $tableBorder . '"';
        }
        return '<table' . $attrs . '>';
    }, $html);

    // Add styles to <td> tags
    $html = preg_replace_callback('/<td([^>]*)>/i', function($m) use ($cellBorder) {
        $attrs = $m[1];
        if (stripos($attrs, 'style=') !== false) {
            if (stripos($attrs, 'border') === false) {
                $attrs = preg_replace('/style\s*=\s*["\']([^"\']*)["\']/', 'style="$1;' . $cellBorder . '"', $attrs);
            }
        } else {
            $attrs .= ' style="' . $cellBorder . '"';
        }
        return '<td' . $attrs . '>';
    }, $html);

    // Add styles to <th> tags
    $html = preg_replace_callback('/<th([^>]*)>/i', function($m) use ($cellBorder) {
        $attrs = $m[1];
        $thExtra = 'font-weight:bold;background:#f0f0f0;';
        if (stripos($attrs, 'style=') !== false) {
            if (stripos($attrs, 'border') === false) {
                $attrs = preg_replace('/style\s*=\s*["\']([^"\']*)["\']/', 'style="$1;' . $cellBorder . $thExtra . '"', $attrs);
            }
        } else {
            $attrs .= ' style="' . $cellBorder . $thExtra . '"';
        }
        return '<th' . $attrs . '>';
    }, $html);

    return $html;
}

// Apply table border styles to global body
$body = add_table_borders($body);

// Get current user ID from session
$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) { die('User tidak terautentikasi'); }

$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO mail_jobs(created_by, subject, body, cc, mode, status) VALUES(?, ?, ?, ?, 'by_similarity', 'processing')");
$stmt->execute([$userId, $subject, $body, $cc]);
$jobId = $pdo->lastInsertId();

$preparedItems = [];
$batchInserts = [];  // Batch insert buffer for mail_job_items

foreach ($items as $it) {
    $type = trim($it['type'] ?? 'individual');
    $attachment = trim($it['attachment'] ?? '');
    // Support multiple attachments separated by pipe |
    $attachmentPaths = array_filter(array_map('trim', explode('|', $attachment)));
    $score = 0;
    
    if ($type === 'group') {
        // Group mode: satu email untuk semua members dalam TO
        $toEmails = $it['to_emails'] ?? [];
        $toEmails = array_filter(array_map('trim', $toEmails));
        
        // Per-item subject/body override (PO MTC feature)
        $itemSubject = trim($it['subject'] ?? '');
        $itemBody = trim($it['body'] ?? '');
        if ($itemBody !== '') $itemBody = add_table_borders($itemBody);
        
        if (empty($toEmails)) continue;
        
        // OPTIMIZATION: Batch insert records for all group members
        $relatedItemIds = [];
        foreach ($toEmails as $recipientEmail) {
            $score = 0;
            
            // Queue for batch insert (store all attachment paths joined)
            $batchInserts[] = [
                'mail_job_id' => $jobId,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientEmail,
                'attachment_path' => $attachment,
                'similarity_score' => $score
            ];
        }
        
        // Create satu PowerShell item untuk kirim ke semua members sekaligus
        $toStr = implode(';', $toEmails);
        $item = [
            'type' => 'group',
            'to' => $toStr,
            'attachment' => count($attachmentPaths) > 1 ? $attachmentPaths : $attachment,
            'emails_count' => count($toEmails)
        ];
        // Add per-item subject/body if present (PO MTC)
        if ($itemSubject !== '') $item['subject'] = $itemSubject;
        if ($itemBody !== '') $item['body'] = $itemBody;
        $preparedItems[] = $item;
    } else {
        // Individual mode: satu email per recipient
        $email = trim($it['email'] ?? '');
        $name = trim($it['name'] ?? '');
        $itemSubject = trim($it['subject'] ?? '');
        
        if (empty($email)) continue;
        
        // OPTIMIZATION: Skip similarity score calculation for speed
        $score = 0;  // Default to 0 - disabled for performance
        
        // Queue for batch insert
        $batchInserts[] = [
            'mail_job_id' => $jobId,
            'recipient_email' => $email,
            'recipient_name' => $name,
            'attachment_path' => $attachment,
            'similarity_score' => $score
        ];
        
        $preparedItems[] = [
            'type' => 'individual',
            'email' => $email,
            'to' => $email,
            'attachment' => $attachment,
        ];
        if ($itemSubject !== '') {
            $preparedItems[count($preparedItems) - 1]['subject'] = $itemSubject;
        }
    }
}

// OPTIMIZATION: Batch insert all mail_job_items at once
if (!empty($batchInserts)) {
    $placeholders = array_fill(0, count($batchInserts), '(?, ?, ?, ?, ?, \'pending\')');
    $values = [];
    
    foreach ($batchInserts as $row) {
        $values[] = $row['mail_job_id'];
        $values[] = $row['recipient_email'];
        $values[] = $row['recipient_name'];
        $values[] = $row['attachment_path'];
        $values[] = $row['similarity_score'];
    }
    
    $sql = "INSERT INTO mail_job_items(mail_job_id, recipient_email, recipient_name, attachment_path, similarity_score, status) VALUES " . implode(',', $placeholders);
    $batchStmt = $pdo->prepare($sql);
    $batchStmt->execute($values);
}

// Now re-fetch item IDs and map to preparedItems
if (!empty($preparedItems)) {
    $itemIdsStmt = $pdo->prepare("SELECT recipient_email, id FROM mail_job_items WHERE mail_job_id = ? ORDER BY id");
    $itemIdsStmt->execute([$jobId]);
    $allItemIds = $itemIdsStmt->fetchAll(PDO::FETCH_KEY_PAIR);  // email => id
    
    $itemIndex = 0;
    foreach ($preparedItems as &$item) {
        if ($item['type'] === 'group') {
            // Map group emails to their IDs
            $toEmails = array_map('trim', explode(';', $item['to']));
            $relatedIds = [];
            foreach ($toEmails as $email) {
                if (isset($allItemIds[$email])) {
                    $relatedIds[] = $allItemIds[$email];
                }
            }
            $item['id'] = $relatedIds[0] ?? 0;
            $item['related_ids'] = $relatedIds;
        } else {
            // Individual mode mapping
            $item['id'] = $allItemIds[$item['to']] ?? 0;
        }
    }
}
$pdo->commit();

// Build job JSON
$jobJson = [
    'subject' => $subject,
    'cc' => $cc,
    'body' => $body,
    'items' => $preparedItems,
];
$jobPath = TEMP_DIR . DIRECTORY_SEPARATOR . 'job_' . $jobId . '.json';
file_put_contents($jobPath, json_encode($jobJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

// Build map: PowerShell item ID -> related mail_job_item IDs
$relatedIdsMap = [];
foreach ($preparedItems as $item) {
    if (!empty($item['related_ids']) && is_array($item['related_ids'])) {
        $relatedIdsMap[$item['id']] = $item['related_ids'];
    }
}

$ps = __DIR__ . '/../ps/send_outlook_emails.ps1';
$account = get_sender_account();
$resultPath = TEMP_DIR . DIRECTORY_SEPARATOR . 'result_job_' . $jobId . '.json';
$cmd = 'powershell -ExecutionPolicy Bypass -File ' . escapeshellarg($ps) . ' -JobJsonPath ' . escapeshellarg($jobPath) . ' -Account ' . escapeshellarg($account) . ' -ResultJsonPath ' . escapeshellarg($resultPath);

// --- OPTIMIZATION: Run PowerShell in background (non-blocking)
// This allows PHP to return immediately instead of waiting for all emails to send
$descriptor_spec = array(
   0 => array("pipe", "r"),  // stdin
   1 => array("pipe", "w"),  // stdout
   2 => array("pipe", "w")   // stderr
);
$process = proc_open(
    $cmd . ' 2>&1',
    $descriptor_spec,
    $pipes,
    null,
    null
);

$output = '';
if (is_resource($process)) {
    // Close stdin (not needed for PowerShell)
    fclose($pipes[0]);
    
    // Read initial output (first few lines) to check for immediate errors
    stream_set_blocking($pipes[1], false);
    $initialOutput = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    // Don't wait for process to finish - let it run in background
    // The process will write results to $resultPath JSON file
    $output = $initialOutput;
    
    proc_close($process);
} else {
    $output = "Error: Gagal menjalankan PowerShell process";
}

// --- NOTE: Results will be processed asynchronously
// Check logs.php to see real-time progress
// Do NOT wait for result file here to keep response fast

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Pengiriman Diproses</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:0;background:#f7f7fb;color:#222;font-size:15px;letter-spacing:-0.01em}
header{background:#0d6efd;color:#fff;padding:16px}
main{padding:20px;max-width:1100px;margin:0 auto}
.btn{display:inline-block;background:#0d6efd;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none}
pre{white-space:pre-wrap;background:#f0f2f5;padding:8px;border-radius:6px}
</style>
</head>
<body>
<header>
  <h2>Pengiriman Diproses</h2>
  <div><a class="btn" href="logs.php">⟵ Lihat Rekap</a></div>
</header>
<main>
  <p>Job ID: <?= e($jobId) ?></p>
  <p>Output PowerShell:</p>
  <pre><?= e($output) ?></pre>
  <p><a class="btn" href="logs.php">Buka Log & Rekap</a></p>
</main>
</body>
</html>
