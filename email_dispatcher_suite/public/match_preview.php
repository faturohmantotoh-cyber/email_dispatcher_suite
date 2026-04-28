<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';
ensure_dirs();
$pdo = DB::conn();

$subject = $_POST['subject'] ?? '';
$cc = $_POST['cc'] ?? '';
$body = $_POST['body'] ?? '';
$threshold = max(0, min(100, (int)($_POST['threshold'] ?? 60)));
$recips = $_POST['recipients'] ?? [];
$selectedGroupIdsStr = $_POST['selected_group_ids'] ?? '';
$orderConsumableMode = ($_POST['order_consumable_mode'] ?? '0') === '1';

// PO MTC per-group data from compose.php
$poMtcDataRaw = $_POST['po_mtc_data'] ?? '';
$poMtcData = [];
if (!empty($poMtcDataRaw)) {
    $decoded = json_decode($poMtcDataRaw, true);
    if (is_array($decoded)) {
        // Index by group_id for easy lookup
        foreach ($decoded as $entry) {
            $gid = (int)($entry['group_id'] ?? 0);
            if ($gid > 0) $poMtcData[$gid] = $entry;
        }
    }
}

// Parse selected group IDs dari form
$selectedGroupIds = [];
if (!empty($selectedGroupIdsStr)) {
    $selectedGroupIds = array_filter(
        array_map('intval', explode(',', $selectedGroupIdsStr)),
        fn($v) => $v > 0
    );
}

$attachments = $pdo->query("SELECT * FROM attachments ORDER BY uploaded_at DESC")->fetchAll();

/**
 * Generate per-group body for PO MTC.
 * Uses regex to find "Dear" and "Dengan Nomor" in the HTML body and inserts
 * supplier name / PO list after them. Also supports placeholder fallback.
 */
function generate_po_mtc_body(string $templateBody, string $supplierName, array $poNumbers, string $attachmentPath = ''): string {
    $result = $templateBody;
    $safeSupplier = htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8');

    // --- 1. Insert supplier name after "Dear" ---
    // Placeholder mode: {SUPPLIER_NAME}
    if (strpos($result, '{SUPPLIER_NAME}') !== false) {
        $result = str_replace('{SUPPLIER_NAME}', $safeSupplier, $result);
    } else {
        // Regex mode: find "Dear" followed by optional whitespace/&nbsp;/HTML tags, then comma/period
        // Handles: "Dear ,", "Dear,", "Dear&nbsp;,", "Dear</p><p>,", "Dear "
        $result = preg_replace(
            '/(Dear)(\s|&nbsp;|<[^>]*>)*([,.])/i',
            '$1 ' . preg_quote($safeSupplier, '/') . '$3',
            $result,
            1
        );
        // Fallback: if no comma/period found after Dear, just append after "Dear"
        if (strpos($result, 'Dear ' . $safeSupplier) === false) {
            $result = preg_replace(
                '/(Dear)(\s|&nbsp;)*/i',
                '$1 ' . preg_quote($safeSupplier, '/') . ',',
                $result,
                1
            );
        }
    }

    // --- 2. Extract PO numbers from attachment filename as fallback ---
    if (empty($poNumbers) && !empty($attachmentPath)) {
        $basename = basename($attachmentPath);
        if (preg_match_all('/\d{7,}/', $basename, $m)) {
            $poNumbers = $m[0];
        }
    }

    // --- 3. Build PO list HTML ---
    $poListHtml = '';
    if (!empty($poNumbers)) {
        $poListHtml = '<ol>';
        foreach ($poNumbers as $po) {
            $poListHtml .= '<li>' . htmlspecialchars($po, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $poListHtml .= '</ol>';
    }

    // --- 4. Insert PO list after "Dengan Nomor" ---
    if (strpos($result, '{PO_LIST}') !== false) {
        $result = str_replace('{PO_LIST}', $poListHtml, $result);
    } elseif (!empty($poListHtml)) {
        // Regex mode: find "Dengan Nomor" + optional colon, then insert PO list after it
        // Close the current HTML tag context first, then append the list
        $result = preg_replace(
            '/(Dengan\s+Nomor\s*:?\s*)/i',
            '$1' . $poListHtml,
            $result,
            1
        );
    }

    return $result;
}

/**
 * Get recipient's groups from database
 * Query: email → contacts.id → group_members → groups
 */
function get_recipient_groups(PDO $pdo, string $email) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT g.id, g.name
            FROM `group_members` gm
            JOIN `groups` g ON g.id = gm.group_id
            JOIN `contacts` c ON c.id = gm.contact_id
            WHERE c.email = ?
            ORDER BY g.name
        ");
        $stmt->execute([$email]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Groups table may not exist
        return [];
    }
}

/**
 * Enhanced similarity with group consideration
 * @param string $filename Attachment filename
 * @param string $email Recipient email
 * @param string $name Recipient display name
 * @param array $recipientGroups Array of [id, name] from database
 * @return float Similarity score 0-100
 */
function enhanced_similarity($filename, $email, $name, $recipientGroups) {
    // Base similarity: compare filename with email + name
    $score1 = similarity_score($filename, $email);
    $score2 = similarity_score($filename, $name);
    $baseScore = max($score1, $score2);
    
    // If recipient has groups, check if filename matches any group name
    // GROUP MATCH SHOULD BE PRIORITIZED!
    if (!empty($recipientGroups)) {
        $maxGroupScore = 0;
        $bestGroupName = '';
        
        foreach ($recipientGroups as $g) {
            $groupName = $g['name'] ?? '';
            if ($groupName) {
                // Check 1: Direct similarity
                $groupScore = similarity_score($filename, $groupName);
                
                // Check 2: Substring match (e.g., "3M" in "3M Indonesia...")
                $normalizedName = strtolower($groupName);
                $normalizedFile = strtolower($filename);
                if (strpos($normalizedFile, $normalizedName) !== false) {
                    // Substring found: boost score significantly
                    $groupScore = max($groupScore, 75);
                }
                
                if ($groupScore > $maxGroupScore) {
                    $maxGroupScore = $groupScore;
                    $bestGroupName = $groupName;
                }
            }
        }
        
        // If group match exists, prioritize it over email/name match
        if ($maxGroupScore >= 30) {
            // Group match is significant, boost it with generous bonus
            return min($maxGroupScore + 25, 100);
        }
    }
    
    return min($baseScore, 100);
}

/**
 * Extract supplier display name from attachment filename.
 * Example: "DN_ORDER_CONSUMABLE_SUPPLIER_4100129502.pdf" -> "SUPPLIER"
 */
function extract_supplier_from_attachment_path(string $attachmentPath): string {
  if ($attachmentPath === '') return '';

  $base = pathinfo(basename($attachmentPath), PATHINFO_FILENAME);
  $name = str_replace(['_', '-', '.'], ' ', $base);

  // Remove common non-supplier tokens
  $name = preg_replace('/\b(dn|po|purchase|order|consumable|adm|kap|rev|revisi|final|copy|scan|doc|pdf|xlsx|xls)\b/i', ' ', $name);
  // Remove long numeric fragments (PO numbers, dates, etc)
  $name = preg_replace('/\b\d{3,}\b/', ' ', $name);
  $name = preg_replace('/\s+/', ' ', $name);
  $name = trim($name);

  return $name;
}

function build_order_consumable_subject(string $baseSubject, string $attachmentPath, string $fallbackSupplier = ''): string {
  $subject = trim($baseSubject);
  $supplier = trim(extract_supplier_from_attachment_path($attachmentPath));
  if ($supplier === '') {
    $supplier = trim($fallbackSupplier);
  }

  if ($supplier === '') {
    return $subject;
  }
  return $subject . ' (' . $supplier . ')';
}

$mappings = [];

// Jika ada selected groups: group recipients by group
if (!empty($selectedGroupIds)) {
    // Build map: group_id -> list of recipients in that group
    $groupMembers = [];
    $ungroupedRecips = [];
    
    foreach ($recips as $r) {
        $parts = explode('|', $r, 3);
        $email = trim($parts[0] ?? '');
        $name = trim($parts[1] ?? '');
        
        if (empty($email)) continue;
        
        // Query database untuk cek email ini masuk ke grup mana (dari selected groups)
        $recipientGroups = get_recipient_groups($pdo, $email);
        
        // Cek apakah email ini masuk ke salah satu selected group
        $groupIds = [];
        foreach ($recipientGroups as $g) {
            if (in_array($g['id'], $selectedGroupIds)) {
                $groupIds[] = $g['id'];
            }
        }
        
        if (!empty($groupIds)) {
            // Recipient ini masuk ke selected group
            $primaryGroupId = $groupIds[0];  // Gunakan group pertama sebagai primary
            if (!isset($groupMembers[$primaryGroupId])) {
                $groupMembers[$primaryGroupId] = [];
            }
            $groupMembers[$primaryGroupId][] = [
                'email' => $email,
                'name' => $name,
                'groups' => $recipientGroups,
            ];
        } else {
            // Recipient ini tidak masuk ke selected group (manual select)
            $ungroupedRecips[] = [
                'email' => $email,
                'name' => $name,
                'groups' => $recipientGroups,
            ];
        }
    }
    
    // Process group members: satu email per group dengan semua members di CC
    $itemIndex = 0;
    foreach ($groupMembers as $groupId => $members) {
        if (empty($members)) continue;
        
        // Cari ALL matching attachments untuk group ini (score >= threshold)
        $primaryMember = $members[0];
        $matchedAttachments = [];
        $bestScore = -1;
        
        foreach ($attachments as $a) {
            $score = enhanced_similarity($a['filename'], $primaryMember['email'], $primaryMember['name'], $primaryMember['groups']);
            
            if ($score >= $threshold) {
                $matchedAttachments[] = ['path' => $a['path'], 'filename' => $a['filename'], 'score' => $score];
            }
            if ($score > $bestScore) {
                $bestScore = $score;
            }
        }
        
        // Sort by score descending
        usort($matchedAttachments, fn($a, $b) => $b['score'] <=> $a['score']);
        
        // Skip group jika ada attachment tapi tidak ada yang match threshold
        if (!empty($attachments) && empty($matchedAttachments)) {
            continue;
        }
        
        // Create one mapping for the entire group (all members in CC)
        $groupName = '';
        foreach ($primaryMember['groups'] as $g) {
            if ($g['id'] == $groupId) {
                $groupName = $g['name'];
                break;
            }
        }
        
        // Build attachment paths array and display string
        $attachmentPaths = array_column($matchedAttachments, 'path');
        $topScore = !empty($matchedAttachments) ? $matchedAttachments[0]['score'] : 0;
        $topAttachmentPath = !empty($attachmentPaths) ? $attachmentPaths[0] : '';
        
        // PO MTC per-group overrides
        $itemSubject = '';
        $itemBody = '';
        if (!empty($poMtcData)) {
            $supplierName = $poMtcData[$groupId]['supplier_name'] ?? $groupName;
            $poNums = $poMtcData[$groupId]['po_numbers'] ?? [];
            // Extract PO numbers from ALL matched attachment filenames as fallback
            if (empty($poNums) && !empty($attachmentPaths)) {
                foreach ($attachmentPaths as $aPath) {
                    $bn = basename($aPath);
                    if (preg_match_all('/\d{7,}/', $bn, $mm)) {
                        $poNums = array_merge($poNums, $mm[0]);
                    }
                }
                $poNums = array_unique($poNums);
            }
            $itemSubject = 'PURCHASE ORDER ADM KAP - (' . $supplierName . ')';
            $itemBody = generate_po_mtc_body($body, $supplierName, $poNums, !empty($attachmentPaths) ? $attachmentPaths[0] : '');
        }

          // ORDER CONSUMABLE per-item subject override from matched attachment
          if ($orderConsumableMode && $itemSubject === '') {
            $itemSubject = build_order_consumable_subject($subject, $topAttachmentPath, $groupName);
          }

        $mappings[] = [
            'type' => 'group',
            'group_id' => $groupId,
            'group_name' => $groupName,
            'primary_email' => $primaryMember['email'],
            'primary_name' => $primaryMember['name'],
            'member_emails' => array_map(fn($m) => $m['email'], $members),
            'member_names' => array_map(fn($m) => $m['name'], $members),
            'attachment' => implode('|', $attachmentPaths),
            'attachments' => $matchedAttachments,
            'score' => $topScore,
            'item_subject' => $itemSubject,
            'item_body' => $itemBody,
        ];
    }
    
    // Process ungrouped recipients (individually)
    foreach ($ungroupedRecips as $r) {
        $email = $r['email'];
        $name = $r['name'];
        $recipientGroups = $r['groups'];
        
        $best = null;
        $bestScore = -1;
        $bestPath = null;
        
        foreach ($attachments as $a) {
            $score = enhanced_similarity($a['filename'], $email, $name, $recipientGroups);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $a;
                $bestPath = $a['path'];
            }
        }
        
        // Skip jika ada attachment tapi score di bawah threshold
        if (!empty($attachments) && $bestScore < $threshold) {
            continue;
        }
        
        $groupNames = array_column($recipientGroups, 'name');
        $itemSubject = '';
        if ($orderConsumableMode) {
          $fallbackSupplier = !empty($groupNames) ? $groupNames[0] : $name;
          $itemSubject = build_order_consumable_subject($subject, ($bestScore >= $threshold) ? $bestPath : '', $fallbackSupplier);
        }
        
        $mappings[] = [
            'type' => 'individual',
            'email' => $email,
            'name' => $name,
            'group_names' => $groupNames,
            'attachment' => ($bestScore >= $threshold) ? $bestPath : '',
            'score' => $bestScore,
          'item_subject' => $itemSubject,
        ];
    }
} else {
    // No groups selected: process each recipient individually
    foreach ($recips as $r) {
        $parts = explode('|', $r, 3);
        $email = trim($parts[0] ?? '');
        $name = trim($parts[1] ?? '');
        
        if (empty($email)) continue;
        
        // Query database untuk cek email ini masuk ke grup mana
        $recipientGroups = get_recipient_groups($pdo, $email);
        
        $best = null; 
        $bestScore = -1; 
        $bestPath = null;
        
        foreach ($attachments as $a) {
            $score = enhanced_similarity($a['filename'], $email, $name, $recipientGroups);
            if ($score > $bestScore) { 
                $bestScore = $score; 
                $best = $a; 
                $bestPath = $a['path']; 
            }
        }
        
        // Skip jika ada attachment tapi score di bawah threshold
        if (!empty($attachments) && $bestScore < $threshold) {
            continue;
        }
        
        // Extract group names from database result
        $groupNames = array_column($recipientGroups, 'name');
        $itemSubject = '';
        if ($orderConsumableMode) {
          $fallbackSupplier = !empty($groupNames) ? $groupNames[0] : $name;
          $itemSubject = build_order_consumable_subject($subject, ($bestScore >= $threshold) ? $bestPath : '', $fallbackSupplier);
        }
        
        $mappings[] = [
            'type' => 'individual',
            'email' => $email,
            'name' => $name,
            'group_names' => $groupNames,
            'attachment' => ($bestScore >= $threshold) ? $bestPath : '',
            'score' => $bestScore,
          'item_subject' => $itemSubject,
        ];
    }
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Preview Pencocokan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:0;background:#f7f7fb;color:#222;font-size:15px;letter-spacing:-0.01em}
header{background:#0d6efd;color:#fff;padding:16px}
main{padding:20px;max-width:1100px;margin:0 auto}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left}
.btn{display:inline-block;background:#0d6efd;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:16px}
.card h3{margin:0;padding:12px 16px;border-bottom:1px solid #e5e7eb}
.card .body{padding:16px}
.badge{padding:2px 6px;border-radius:4px;font-size:12px}
.badge.ok{background:#16a34a;color:#fff}
.badge.err{background:#dc2626;color:#fff}
</style>
</head>
<body>
<header>
  <h2>Preview Pencocokan</h2>
  <div><a class="btn" href="compose.php">⟵ Kembali</a></div>
</header>
<main>
  <div class="card"><h3>Rencana Pengiriman</h3><div class="body">
    <form method="post" action="send.php">
      <input type="hidden" name="subject" value="<?= e($subject) ?>">
      <input type="hidden" name="cc" value="<?= e($cc) ?>">
      <textarea name="body" style="display:none"><?= e($body) ?></textarea>
      <input type="hidden" name="threshold" value="<?= e($threshold) ?>">
      <input type="hidden" name="selected_group_ids" value="<?= e($selectedGroupIdsStr) ?>">
      <input type="hidden" name="order_consumable_mode" value="<?= $orderConsumableMode ? '1' : '0' ?>">
      
      <table class="table">
        <thead><tr><th>Penerima</th><th>Grup</th><th>Email</th><th>Lampiran</th><th>Skor</th></tr></thead>
        <tbody>
        <?php foreach ($mappings as $i => $m): ?>
          <?php if ($m['type'] === 'group'): ?>
            <!-- Group row -->
            <tr style="background:#f0f9ff;border-left:4px solid #0d6efd;">
              <td><strong><?= e($m['primary_name']) ?></strong> + <?= count($m['member_emails']) - 1 ?> lainnya</td>
              <td>
                <div style="padding:2px 6px;background:#e3f2fd;border-radius:3px;font-size:11px;display:inline-block;margin-right:4px;">
                  <?= e($m['group_name']) ?>
                </div>
                <?php if (!empty($m['item_subject'])): ?>
                  <div style="font-size:10px;color:#0d6efd;margin-top:4px;" title="Per-group subject">📧 <?= e($m['item_subject']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span style="color:#666;font-size:11px;">To: 
                  <?= e(implode(', ', array_slice($m['member_emails'], 0, 2))) ?>
                  <?= count($m['member_emails']) > 2 ? '...' : '' ?>
                </span>
              </td>
              <td>
                <?php if ($m['attachment']): ?>
                  <?php foreach ($m['attachments'] as $att): ?>
                    <div style="font-size:12px;margin-bottom:2px;">📎 <?= e(basename($att['path'])) ?> <span class="badge ok"><?= (int)$att['score'] ?></span></div>
                  <?php endforeach; ?>
                  <input type="hidden" name="items[<?= $i ?>][type]" value="group">
                  <input type="hidden" name="items[<?= $i ?>][group_id]" value="<?= (int)$m['group_id'] ?>">
                  <input type="hidden" name="items[<?= $i ?>][attachment]" value="<?= e($m['attachment']) ?>">
                  <?php foreach ($m['member_emails'] as $mem_email): ?>
                    <input type="hidden" name="items[<?= $i ?>][to_emails][]" value="<?= e($mem_email) ?>">
                  <?php endforeach; ?>
                  <?php if (!empty($m['item_subject'])): ?>
                    <input type="hidden" name="items[<?= $i ?>][subject]" value="<?= e($m['item_subject']) ?>">
                  <?php endif; ?>
                  <?php if (!empty($m['item_body'])): ?>
                    <textarea name="items[<?= $i ?>][body]" style="display:none"><?= e($m['item_body']) ?></textarea>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge err">Tidak ada match (di bawah threshold)</span>
                  <input type="hidden" name="items[<?= $i ?>][type]" value="group">
                  <input type="hidden" name="items[<?= $i ?>][group_id]" value="<?= (int)$m['group_id'] ?>">
                  <input type="hidden" name="items[<?= $i ?>][attachment]" value="">
                  <?php foreach ($m['member_emails'] as $mem_email): ?>
                    <input type="hidden" name="items[<?= $i ?>][to_emails][]" value="<?= e($mem_email) ?>">
                  <?php endforeach; ?>
                  <?php if (!empty($m['item_subject'])): ?>
                    <input type="hidden" name="items[<?= $i ?>][subject]" value="<?= e($m['item_subject']) ?>">
                  <?php endif; ?>
                  <?php if (!empty($m['item_body'])): ?>
                    <textarea name="items[<?= $i ?>][body]" style="display:none"><?= e($m['item_body']) ?></textarea>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $m['score'] >= $threshold ? 'ok' : 'err' ?>"><?= e($m['score']) ?></span></td>
            </tr>
          <?php else: ?>
            <!-- Individual row -->
            <tr>
              <td><?= e($m['name']) ?></td>
              <td>
                <?php if (!empty($m['group_names'])): ?>
                  <?php foreach (array_filter($m['group_names']) as $gn): ?>
                    <div style="padding:2px 6px;background:#e3f2fd;border-radius:3px;font-size:11px;margin-bottom:4px;display:inline-block;margin-right:4px;"><?= e($gn) ?></div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <span style="color:#999;font-size:11px;">—</span>
                <?php endif; ?>
                <?php if (!empty($m['item_subject'])): ?>
                  <div style="font-size:10px;color:#0d6efd;margin-top:4px;" title="Per-item subject">📧 <?= e($m['item_subject']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($m['email']) ?><input type="hidden" name="items[<?= $i ?>][type]" value="individual"><input type="hidden" name="items[<?= $i ?>][email]" value="<?= e($m['email']) ?>"><input type="hidden" name="items[<?= $i ?>][name]" value="<?= e($m['name']) ?>"></td>
              <td>
                <?php if ($m['attachment']): ?>
                  <?= e(basename($m['attachment'])) ?>
                  <input type="hidden" name="items[<?= $i ?>][attachment]" value="<?= e($m['attachment']) ?>">
                <?php else: ?>
                  <span class="badge err">Tidak ada match (di bawah threshold)</span>
                  <input type="hidden" name="items[<?= $i ?>][attachment]" value="">
                <?php endif; ?>
                <?php if (!empty($m['item_subject'])): ?>
                  <input type="hidden" name="items[<?= $i ?>][subject]" value="<?= e($m['item_subject']) ?>">
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $m['score'] >= $threshold ? 'ok' : 'err' ?>"><?= e($m['score']) ?></span></td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p><button class="btn" type="submit">5) Kirim Email</button></p>
    </form>
  </div></div>
</main>
</body>
</html>
