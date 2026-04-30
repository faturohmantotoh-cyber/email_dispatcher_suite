<?php
// compose.php — Drop-in replacement (Laragon / PHP / MySQL 8)
// Fitur:
// - Filter Grup (dropdown + checkbox) + Badge nama grup & jumlah grup terpilih
// - Jika grup dipilih → auto recipients by grup (tanpa ceklis manual)
// - Jika tidak ada grup → boleh ceklis kontak manual
// - Hidden inputs recipients[] disinkron otomatis (kompatibel dengan match_preview.php)
// - Auto-refresh grup & kontak saat slider dibuka
// - Backtick untuk `groups` & `group_members` (keyword-safe MySQL 8)

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';

ensure_dirs();
$pdo = DB::conn();

function ensure_group_tables(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `groups` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_groups_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `group_members` (
            `group_id` INT NOT NULL,
            `contact_id` INT NOT NULL,
            `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`group_id`, `contact_id`),
            CONSTRAINT `fk_gm_group` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_gm_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensure_group_order_tables(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `group_orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_group_orders_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `group_order_items` (
            `group_order_id` INT NOT NULL,
            `group_id` INT NOT NULL,
            `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`group_order_id`, `group_id`),
            CONSTRAINT `fk_goi_group_order` FOREIGN KEY (`group_order_id`) REFERENCES `group_orders`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_goi_group` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensure_cc_group_tables(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cc_groups` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_cc_groups_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cc_group_members` (
            `cc_group_id` INT NOT NULL,
            `contact_id` INT NOT NULL,
            `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`cc_group_id`, `contact_id`),
            CONSTRAINT `fk_ccgm_group` FOREIGN KEY (`cc_group_id`) REFERENCES `cc_groups`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ccgm_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// ===== AJAX Endpoints =====
if (isset($_GET['ajax'])) {
    $ajax = (string)($_GET['ajax'] ?? '');

    if ($ajax === 'groups') {
        try {
            ensure_group_tables($pdo);
            $rows = $pdo->query("
                SELECT g.`id`, g.`name`, COUNT(gm.`contact_id`) AS members
                FROM `groups` g
                LEFT JOIN `group_members` gm ON gm.`group_id` = g.`id`
                GROUP BY g.`id`, g.`name`
                ORDER BY g.`name`
            ")->fetchAll(PDO::FETCH_ASSOC);
            json_response(['ok' => true, 'data' => $rows]);
        } catch (Exception $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    if ($ajax === 'contacts') {
        try {
            ensure_group_tables($pdo);
            // Multi grup: ?group_ids=1,2,3 (kosong -> semua kontak)
            $idsParam = trim($_GET['group_ids'] ?? '');
            $groupIds = array_values(array_unique(
                array_filter(
                    array_map('intval', explode(',', $idsParam)),
                    fn($v) => $v > 0
                )
            ));

            if (!empty($groupIds)) {
                $in = implode(',', array_fill(0, count($groupIds), '?'));
                $sql = "
                    SELECT DISTINCT c.`id`, c.`display_name`, c.`email`, c.`source`
                    FROM `group_members` gm
                    JOIN `contacts` c ON c.`id` = gm.`contact_id`
                    WHERE gm.`group_id` IN ($in)
                    ORDER BY c.`display_name`
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($groupIds);
                $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $contacts = $pdo->query("
                    SELECT `id`, `display_name`, `email`, `source`
                    FROM `contacts`
                    ORDER BY `display_name`
                ")->fetchAll(PDO::FETCH_ASSOC);
            }

            json_response(['ok' => true, 'data' => $contacts]);
        } catch (Exception $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    if ($ajax === 'group_orders') {
        try {
            ensure_group_order_tables($pdo);
            $rows = $pdo->query("
                SELECT go.`id`, go.`name`, COUNT(goi.`group_id`) AS group_count
                FROM `group_orders` go
                LEFT JOIN `group_order_items` goi ON goi.`group_order_id` = go.`id`
                GROUP BY go.`id`, go.`name`
                ORDER BY go.`name`
            ")->fetchAll(PDO::FETCH_ASSOC);
            json_response(['ok' => true, 'data' => $rows]);
        } catch (Exception $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    if ($ajax === 'cc_groups') {
        try {
            ensure_cc_group_tables($pdo);
            $rows = $pdo->query("
                SELECT cg.`id`, cg.`name`, COUNT(cgm.`contact_id`) AS members
                FROM `cc_groups` cg
                LEFT JOIN `cc_group_members` cgm ON cgm.`cc_group_id` = cg.`id`
                GROUP BY cg.`id`, cg.`name`
                ORDER BY cg.`name`
            ")->fetchAll(PDO::FETCH_ASSOC);

            // For each group, load member emails
            $result = [];
            foreach ($rows as $row) {
                $stmt = $pdo->prepare("
                    SELECT c.`email`, c.`display_name`
                    FROM `cc_group_members` cgm
                    JOIN `contacts` c ON c.`id` = cgm.`contact_id`
                    WHERE cgm.`cc_group_id` = ?
                    ORDER BY c.`display_name`
                ");
                $stmt->execute([(int)$row['id']]);
                $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result[] = [
                    'id'      => (int)$row['id'],
                    'name'    => $row['name'],
                    'members' => (int)$row['members'],
                    'emails'  => array_column($members, 'email'),
                ];
            }
            json_response(['ok' => true, 'data' => $result]);
        } catch (Exception $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    if ($ajax === 'group_order_contacts') {
        try {
            ensure_group_order_tables($pdo);
            // Ambil all group ids dari group order
            $groupOrderId = (int)($_GET['group_order_id'] ?? 0);
            if ($groupOrderId <= 0) {
                json_response(['ok' => false, 'error' => 'Invalid group_order_id'], 400);
            }

            // Get all group IDs in this group order
            $stmt = $pdo->prepare("SELECT `group_id` FROM `group_order_items` WHERE `group_order_id` = ?");
            $stmt->execute([$groupOrderId]);
            $groupIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (empty($groupIds)) {
                json_response(['ok' => true, 'data' => [], 'group_ids' => []]);
            }

            // Get all contacts from these groups
            $in = implode(',', array_fill(0, count($groupIds), '?'));
            $sql = "
                SELECT DISTINCT c.`id`, c.`display_name`, c.`email`, c.`source`
                FROM `group_members` gm
                JOIN `contacts` c ON c.`id` = gm.`contact_id`
                WHERE gm.`group_id` IN ($in)
                ORDER BY c.`display_name`
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($groupIds);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            json_response(['ok' => true, 'data' => $contacts, 'group_ids' => $groupIds]);
        } catch (Exception $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ===== GET GROUP ORDER CONTACTS GROUPED BY GROUP =====
    if ($ajax === 'group_order_grouped') {
        try {
            ensure_group_order_tables($pdo);
            $groupOrderId = (int)($_GET['group_order_id'] ?? 0);
            if ($groupOrderId <= 0) {
                json_response(['ok' => false, 'error' => 'Invalid group_order_id'], 400);
            }
            // Get group order name
            $stmtGO = $pdo->prepare("SELECT `name` FROM `group_orders` WHERE `id` = ?");
            $stmtGO->execute([$groupOrderId]);
            $goName = $stmtGO->fetchColumn() ?: '';

            // Get all groups in this order with their names
            $stmt = $pdo->prepare("
                SELECT goi.`group_id`, g.`name` AS group_name
                FROM `group_order_items` goi
                JOIN `groups` g ON g.`id` = goi.`group_id`
                WHERE goi.`group_order_id` = ?
                ORDER BY g.`name`
            ");
            $stmt->execute([$groupOrderId]);
            $groupRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($groupRows as $gr) {
                $gid = (int)$gr['group_id'];
                $gname = $gr['group_name'];
                // Get contacts for this group
                $stmtC = $pdo->prepare("
                    SELECT c.`id`, c.`display_name`, c.`email`
                    FROM `group_members` gm
                    JOIN `contacts` c ON c.`id` = gm.`contact_id`
                    WHERE gm.`group_id` = ?
                    ORDER BY c.`display_name`
                ");
                $stmtC->execute([$gid]);
                $contacts = $stmtC->fetchAll(PDO::FETCH_ASSOC);
                $grouped[] = [
                    'group_id' => $gid,
                    'group_name' => $gname,
                    'contacts' => $contacts,
                ];
            }
            // Get attachments list
            $attachments = $pdo->query("SELECT `id`, `filename`, `path` FROM `attachments` ORDER BY `filename`")->fetchAll(PDO::FETCH_ASSOC);

            json_response(['ok' => true, 'group_order_name' => $goName, 'groups' => $grouped, 'attachments' => $attachments]);
        } catch (Exception $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ===== GET TEMPLATE BY GROUP =====
    if ($ajax === 'get_template_by_group') {
        try {
            $groupId = (int)($_GET['group_id'] ?? 0);
            if ($groupId <= 0) {
                json_response(['ok' => false, 'error' => 'Invalid group_id'], 400);
                return;
            }

            // Cari template yang terkait dengan group ini via template_group_links
            $stmt = $pdo->prepare("
                SELECT et.`id`, et.`name`, et.`description`, et.`body`
                FROM `email_templates` et
                INNER JOIN `template_group_links` tgl ON tgl.`template_id` = et.`id`
                WHERE tgl.`group_id` = ?
                LIMIT 1
            ");
            $stmt->execute([$groupId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                json_response(['ok' => false, 'error' => 'No template found for this group'], 404);
                return;
            }

            json_response(['ok' => true, 'data' => $template]);
        } catch (Exception $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ===== GET TEMPLATE BY GROUP ORDER =====
    if ($ajax === 'get_template_by_group_order') {
        try {
            $groupOrderId = (int)($_GET['group_order_id'] ?? 0);
            if ($groupOrderId <= 0) {
                json_response(['ok' => false, 'error' => 'Invalid group_order_id'], 400);
                return;
            }

            // Cari template yang terkait dengan group order ini via template_group_order_links
            $stmt = $pdo->prepare("
                SELECT et.`id`, et.`name`, et.`description`, et.`body`
                FROM `email_templates` et
                INNER JOIN `template_group_order_links` tgol ON tgol.`template_id` = et.`id`
                WHERE tgol.`group_order_id` = ?
                LIMIT 1
            ");
            $stmt->execute([$groupOrderId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                json_response(['ok' => false, 'error' => 'No template found for this group order'], 404);
                return;
            }

            json_response(['ok' => true, 'data' => $template]);
        } catch (Exception $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ===== DEBUG: Show all templates and links =====
    if ($ajax === 'debug_templates') {
        try {
            // Check tables exist
            $tables = $pdo->query("SHOW TABLES LIKE 'email_templates'")->fetchAll();
            $hasTemplateTable = !empty($tables);
            
            $templates = [];
            $groupOrderLinks = [];
            
            if ($hasTemplateTable) {
                $templates = $pdo->query("SELECT id, name, description, created_at FROM email_templates")->fetchAll(PDO::FETCH_ASSOC);
                $groupOrderLinks = $pdo->query("SELECT * FROM template_group_order_links")->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Also get group orders
            $groupOrders = $pdo->query("SELECT id, name FROM group_orders")->fetchAll(PDO::FETCH_ASSOC);
            $groupOrderItems = $pdo->query("SELECT * FROM group_order_items")->fetchAll(PDO::FETCH_ASSOC);
            
            json_response([
                'ok' => true,
                'has_template_table' => $hasTemplateTable,
                'templates_count' => count($templates),
                'templates' => $templates,
                'group_orders_count' => count($groupOrders),
                'group_orders' => $groupOrders,
                'group_order_items_count' => count($groupOrderItems),
                'group_order_links_count' => count($groupOrderLinks),
                'group_order_links' => $groupOrderLinks
            ]);
        } catch (Exception $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 500);
        }
        return;
    }

    json_response(['ok' => false, 'error' => 'Unknown ajax endpoint'], 404);
}

// ===== Normal page load =====
$csrf = get_csrf_token();
$attachments = $pdo->query("SELECT * FROM attachments ORDER BY uploaded_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$totalContacts = (int)$pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();

// ===== DELETE ATTACHMENT HANDLING =====
if (isset($_POST['delete_attachment'])) {
    $attachId = (int)($_POST['delete_attachment'] ?? 0);
    if ($attachId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
        $stmt->execute([$attachId]);
        $attach = $stmt->fetch();
        if ($attach && file_exists($attach['path'])) {
            // Try to delete with error handling
            if (@unlink($attach['path'])) {
                $pdo->prepare("DELETE FROM attachments WHERE id = ?")->execute([$attachId]);
                $uploadMsg = "✅ File dihapus: " . e($attach['filename']);
            } else {
                // Retry after brief delay
                usleep(500000); // 0.5 detik
                if (@unlink($attach['path'])) {
                    $pdo->prepare("DELETE FROM attachments WHERE id = ?")->execute([$attachId]);
                    $uploadMsg = "✅ File dihapus: " . e($attach['filename']);
                } else {
                    $uploadMsg = "❌ Gagal menghapus file: " . e($attach['filename']) . " (mungkin sedang dibuka aplikasi lain, silakan tutup terlebih dahulu)";
                }
            }
        }
    }
    // Refresh attachment list after delete
    $attachments = $pdo->query("SELECT * FROM attachments ORDER BY uploaded_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

// ===== UPLOAD HANDLING =====
$uploadMsg = $uploadMsg ?? '';
if (!empty($_FILES['files'])) {
    $allowed = ['pdf','xlsx','xls','doc','docx','csv','zip','jpg','jpeg','png','txt'];
    $uploadCount = 0;
    
    // Clear all old files from ATTACHMENTS_DIR
    if (is_dir(ATTACHMENTS_DIR)) {
        $files = scandir(ATTACHMENTS_DIR);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = ATTACHMENTS_DIR . DIRECTORY_SEPARATOR . $file;
                if (is_file($filePath)) {
                    try {
                        if (@unlink($filePath)) {
                            $uploadMsg .= "🗑️ Dihapus: $file\n";
                        } else {
                            sleep(1);
                            @unlink($filePath);
                        }
                    } catch (Exception $e) {
                        // Silent fail
                    }
                }
            }
        }
    }
    
    // Clear all records from attachments table
    $pdo->exec("DELETE FROM attachments");
    
    foreach ($_FILES['files']['error'] as $i => $err) {
        if ($err !== UPLOAD_ERR_OK) continue;
        $tmp = $_FILES['files']['tmp_name'][$i];
        $name = basename($_FILES['files']['name'][$i]);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) { 
            $uploadMsg .= "❌ Lewati $name (ekstensi tidak diizinkan)\n"; 
            continue; 
        }
        
        $data = file_get_contents($tmp);
        $sha1 = sha1($data);
        $dest = ATTACHMENTS_DIR . DIRECTORY_SEPARATOR . $name;
        
        move_uploaded_file($tmp, $dest);
        // Insert attachment record - allows multiple files with same content (same SHA1)
        $stmt = $pdo->prepare("INSERT INTO attachments(filename, path, sha1, size) VALUES(?,?,?,?)");
        $stmt->execute([basename($dest), $dest, $sha1, filesize($dest)]);
        $uploadMsg .= "✅ Upload: " . basename($dest) . " (" . human_filesize(filesize($dest)) . ")\n";
        $uploadCount++;
    }
    
    if ($uploadCount > 0) {
        // Refresh attachment list after upload
        $attachments = $pdo->query("SELECT * FROM attachments ORDER BY uploaded_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if(isset($_POST["clear_attachment"])){
  // Clear all old files from ATTACHMENTS_DIR
    if (is_dir(ATTACHMENTS_DIR)) {
        $files = scandir(ATTACHMENTS_DIR);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = ATTACHMENTS_DIR . DIRECTORY_SEPARATOR . $file;
                if (is_file($filePath)) {
                    try {
                        if (@unlink($filePath)) {
                            $uploadMsg .= "✅ Dihapus: $file\n";
                        } else {
                            usleep(500000);
                            @unlink($filePath);
                            $uploadMsg .= "✅ Dihapus: $file (retry)\n";
                        }
                    } catch (Exception $e) {
                        // Silent fail
                    }
                }
            }
        }
    }
    // Clear all records from attachments table
    $pdo->exec("DELETE FROM attachments");
    $uploadMsg .= "✅ Semua lampiran berhasil dihapus!\n";
    // Refresh attachment list
    $attachments = $pdo->query("SELECT * FROM attachments ORDER BY uploaded_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

$hasAttachments = !empty($attachments);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Kirim Email (Similarity)</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/custom.css?v=3.0">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* --- Base --- */
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:0;background:#f7f7fb;color:#222;font-size:15px;letter-spacing:-0.01em}
header{background:#0d6efd;color:#fff;padding:16px}
main{padding:20px;max-width:1100px;margin:0 auto}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left}

/* --- Quill Editor Table Styling --- */
.ql-editor table { 
  border-collapse: collapse; 
  width: 100%; 
  margin: 8px 0;
}
.ql-editor table, .ql-editor thead, .ql-editor tbody, .ql-editor tr, .ql-editor th, .ql-editor td { 
  border: 1px solid #d1d5db; 
}
.ql-editor th, .ql-editor td { 
  padding: 8px; 
  text-align: left; 
  /* Allow inline styles for background colors and text colors to show through */
}
/* Preserve inline styles from pasted HTML - use inherit so inline styles work */
.ql-editor table th,
.ql-editor table td {
  background-color: inherit;
  color: inherit;
}
/* Default header style if no inline style specified */
.ql-editor thead th { 
  background: #f3f4f6; 
}
/* Preserve text formatting in cells */
.ql-editor table strong,
.ql-editor table b {
  font-weight: 600;
}
.ql-editor table em,
.ql-editor table i {
  font-style: italic;
}
.btn{display:inline-block;background:#0d6efd;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none;border:0;cursor:pointer}
.btn.secondary{background:#6b7280}
.btn.warn{background:#f59e0b}
.btn.success{background:#16a34a}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:16px}
.card h3{margin:0;padding:12px 16px;border-bottom:1px solid #e5e7eb}
.card .body{padding:16px}
label{display:block;margin-top:8px}
.small{color:#6b7280;font-size:12px}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px;margin-left:6px}
.tools{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

/* --- Drawer (Slider Kontak) --- */
.drawer-overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.35);
  opacity:0;visibility:hidden;transition:opacity .2s ease;z-index:9999;
  pointer-events:none;
}
.drawer-overlay.open{opacity:1;visibility:visible;pointer-events:auto}
.drawer{
  position:fixed;top:0;right:0;height:100vh;width:460px;max-width:95vw;
  background:#fff;box-shadow:-10px 0 24px rgba(0,0,0,.12);
  transform:translateX(100%);transition:transform .25s ease;z-index:10000;
  display:flex;flex-direction:column;border-left:1px solid #e5e7eb;
  pointer-events:auto;
}
.drawer-overlay.open .drawer{transform:translateX(0)}
.drawer-header{
  padding:12px 16px;border-bottom:1px solid #e5e7eb;background:#f8fafc;
  display:flex;align-items:center;gap:8px;justify-content:space-between
}
.drawer-title{font-weight:600;margin:0}
.drawer-body{padding:12px 16px;overflow:auto;height:calc(100vh - 56px - 56px)}
.drawer-footer{padding:12px 16px;border-top:1px solid #e5e7eb;background:#f8fafc;display:flex;gap:8px;justify-content:flex-end}
.input{padding:8px;border:1px solid #d1d5db;border-radius:6px}
.input.full{width:100%}

/* Table inside drawer */
.table-sm{font-size:14px}
.table-sm th,.table-sm td{padding:6px}

/* Pills info bar */
.info-bar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:8px 0}
.info-pill{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:999px;padding:4px 8px;font-size:12px;color:#111}

/* Dropdown (filter grup) */
.dropdown{position:relative;display:inline-block}
.dropdown-btn{display:inline-flex;align-items:center;gap:6px}
.dropdown-panel{
  position:absolute;right:0;top:calc(100% + 6px);min-width:320px;max-width:80vw;
  background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);
  padding:10px;z-index:10000;display:none;
}
.dropdown-panel.open{display:block}
.dropdown-panel .panel-head{display:flex;gap:8px;align-items:center;justify-content:space-between;margin-bottom:8px}
.dropdown-panel .panel-body{max-height:280px;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;padding:6px}
.dropdown-panel .panel-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}
.grp-row{display:flex;align-items:center;gap:8px;padding:4px 2px}
.grp-row label{display:flex;align-items:center;gap:8px;cursor:pointer}
.grp-row .muted{color:#6b7280;font-size:12px}
.grp-row + .grp-row{border-top:1px dashed #eee}

/* Group badges */
.badges{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.chip{
  display:inline-flex;align-items:center;gap:6px;
  padding:2px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;
  font-size:12px;border:1px solid #e5e7eb;
}
.chip .x{cursor:pointer;font-weight:bold;color:#6b7280}
.chip .x:hover{color:#111}

/* Mode "by group" hint */
.hint{background:#f0fdf4;border:1px solid #bbf7d0;color:#065f46;padding:6px 8px;border-radius:6px;font-size:12px}

/* Responsive */
@media (max-width:640px){
  .drawer{width:100vw}
  .dropdown-panel{left:0;right:auto}
}

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
  z-index: 8999;
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

/* Collapsible Slider Card */
.card-collapsible h3.slider-toggle { cursor: pointer; user-select: none; display: flex; align-items: center; justify-content: space-between; }
.card-collapsible h3.slider-toggle .toggle-icon { font-size: 1.1rem; transition: transform 0.3s ease; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: rgba(0,82,204,0.1); }
.card-collapsible.collapsed h3.slider-toggle .toggle-icon { transform: rotate(-90deg); }
.card-collapsible .card-slider { max-height: 2000px; overflow: hidden; transition: max-height 0.45s cubic-bezier(0.4,0,0.2,1), opacity 0.3s ease; opacity: 1; }
.card-collapsible.collapsed .card-slider { max-height: 0; opacity: 0; }
.card-collapsible h3.slider-toggle .slider-hint { font-size: 0.78rem; font-weight: 500; color: #90A4AE; margin-left: auto; margin-right: 0.5rem; opacity: 0; transition: opacity 0.3s; }
.card-collapsible.collapsed h3.slider-toggle .slider-hint { opacity: 1; }
</style>

<script>
// ======= State =======
let selectedSet = new Set();            // untuk pilihan manual (saat tidak by group)
let currentGroupIds = new Set();        // jika >0 → mode by group
let currentGroupNames = new Map();      // gid -> name (untuk badge)
let currentGroupName = '';              // Track nama grup untuk subject
let latestGroupedContacts = [];         // cache hasil gabungan by grup
let drawerMode = 'recipients';          // 'recipients' atau 'cc'
let selectedCCSet = new Set();          // untuk pilihan CC
let poMtcData = null;                   // data PO MTC per-group (jika aktif)
let isOrderConsumableTemplate = false;  // mode subject khusus ORDER CONSUMABLE

// ======= Helpers =======
function setTextAll(selector, text) { document.querySelectorAll(selector).forEach(el => el.textContent = text); }
function updateSelectedCount(){
  if (currentGroupIds.size > 0) {
    setTextAll('#selectedCount', String(latestGroupedContacts.length));
  } else {
    setTextAll('#selectedCount', String(selectedSet.size));
  }
}
function updateVisibleCount(){
  const rows = document.querySelectorAll('#contactTable tbody tr');
  let visible = 0; rows.forEach(tr => { if (tr.style.display !== 'none') visible++; });
  setTextAll('#visibleCount', String(visible));
}
function setTotalCount(n){ setTextAll('#totalCount', String(n)); }
function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

// ======= Group summary (badge & count) =======
function renderGroupSummary(){
  const n = currentGroupIds.size;
  setTextAll('#groupCount', String(n));

  // Update label tombol dropdown
  const btn = document.getElementById('groupDropdownBtn');
  if (btn) btn.innerHTML = n > 0 ? `Filter Grup (${n}) ▾` : `Filter Grup ▾`;

  // Render badges
  const wrap = document.getElementById('groupBadges');
  if (wrap){
    if (n === 0) {
      wrap.innerHTML = '';
      return;
    }
    let html = '';
    currentGroupIds.forEach(gid => {
      const name = currentGroupNames.get(gid) || `Grup #${gid}`;
      html += `<span class="chip" data-gid="${gid}">${escapeHtml(name)} <span class="x" title="Hapus">×</span></span>`;
    });
    wrap.innerHTML = html;
  }
  
  // Update hidden input dengan selected group IDs
  const groupIdInput = document.getElementById('selectedGroupIds');
  if (groupIdInput) {
    groupIdInput.value = [...currentGroupIds].join(',');
  }
}

// ======= Hidden recipients sync =======
function syncHiddenRecipients(){
  const host = document.getElementById('hiddenRecipients');
  if (!host) return;
  host.innerHTML = '';

  // Mode by group → jadikan seluruh anggota grup sebagai recipients
  if (currentGroupIds.size > 0) {
    for (const c of latestGroupedContacts) {
      const name = String(c.display_name || '').trim();
      const email = String(c.email || '').trim();
      const val = `${email}|${name}`;
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'recipients[]';
      inp.value = val;
      host.appendChild(inp);
    }
    return;
  }

  // Mode manual (tanpa grup)
  for (const val of selectedSet) {
    const inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = 'recipients[]';
    inp.value = val;
    host.appendChild(inp);
  }
}

// ======= Drawer open/close =======
function openDrawer(mode){
  if (!mode) mode = 'recipients';
  drawerMode = mode;
  document.getElementById('drawerOverlay').classList.add('open');
  
  // Update title dan buttons sesuai mode
  const drawerTitle = document.getElementById('drawerTitle');
  const btnGroupsApply = document.getElementById('btnGroupsApply');
  const drawerTip = document.getElementById('drawerTip');
  const recipientsModeButtons = document.getElementById('recipientsModeButtons');
  const ccPillInfo = document.getElementById('ccPillInfo');
  const contactSearch = document.getElementById('contactSearch');
  
  if (mode === 'cc') {
    if (drawerTitle) drawerTitle.textContent = 'Pilih Email untuk CC';
    if (btnGroupsApply) btnGroupsApply.textContent = 'Terapkan CC';
    if (drawerTip) drawerTip.textContent = 'Pilih satu atau lebih email untuk ditambahkan ke field CC.';
    if (recipientsModeButtons) recipientsModeButtons.style.display = 'none';
    if (ccPillInfo) {
      ccPillInfo.style.display = 'inline-flex';
      document.getElementById('selectedCCCount').textContent = selectedCCSet.size;
    }
    // Clear search box setiap kali membuka CC mode
    if (contactSearch) {
      contactSearch.value = '';
      contactSearch.focus();
    }
    document.getElementById('groupDropdownBtn').style.display = 'none';
    document.getElementById('groupBadges').style.display = 'none';
    // Show Grup CC panel and load
    const ccGroupPanel = document.getElementById('ccGroupPanel');
    if (ccGroupPanel) ccGroupPanel.style.display = '';
    loadCCGroupsForDrawer();
    loadContactsForCC();
  } else {
    if (drawerTitle) drawerTitle.textContent = 'Kontak';
    if (btnGroupsApply) btnGroupsApply.textContent = 'Terapkan';
    if (drawerTip) drawerTip.textContent = 'Tip: Pilih Grup untuk auto recipients, atau biarkan kosong untuk pilih manual.';
    if (recipientsModeButtons) recipientsModeButtons.style.display = 'flex';
    if (ccPillInfo) ccPillInfo.style.display = 'none';
    if (contactSearch) {
      contactSearch.value = '';
      contactSearch.focus();
    }
    // Hide Grup CC panel in recipients mode
    const ccGroupPanel = document.getElementById('ccGroupPanel');
    if (ccGroupPanel) ccGroupPanel.style.display = 'none';
    document.getElementById('groupDropdownBtn').style.display = 'inline-flex';
    document.getElementById('groupBadges').style.display = 'flex';
    
    Promise.all([loadGroupOrders(), loadGroups()]).then(() => {
      if (currentGroupIds.size > 0) {
        loadContactsMulti(currentGroupIds);
      } else {
        loadContactsMulti(new Set());
      }
    });
  }
}

function closeDrawer(){
  document.getElementById('drawerOverlay').classList.remove('open');
  closeGroupDropdown();
}

function applyAndCloseDrawer(){
  // Trigger apply untuk mode CC
  if (drawerMode === 'cc') {
    const ccField = document.querySelector('input[name="cc"]');
    if (selectedCCSet.size === 0) {
      alert('⚠️ Pilih minimal satu email untuk ditambahkan ke CC');
      return;
    }
    
    if (ccField) {
      const selectedEmails = Array.from(selectedCCSet);
      const currentCCs = ccField.value.trim().split(';').map(s => s.trim()).filter(s => s);
      const allCCs = [...new Set([...currentCCs, ...selectedEmails])];
      ccField.value = allCCs.join('; ');
      console.log('✅ CC updated:', selectedCCSet.size, 'emails added');
    }
    closeDrawer();
  } else {
    // Untuk mode recipients, langsung tutup saja (selection sudah auto-sync ke hidden input)
    closeDrawer();
  }
}

// ======= AJAX loaders =======
async function loadGroupOrders(){
  try {
    const res = await fetch('?ajax=group_orders', {cache:'no-store'});
    const json = await res.json();
    const list = document.getElementById('groupOrderList');
    if (!list) return;

    let html = '';

    if (json.ok && Array.isArray(json.data) && json.data.length > 0) {
      for (const go of json.data) {
        const goid = parseInt(go.id,10) || 0;
        const grpCount = parseInt(go.group_count,10) || 0;
        const name = String(go.name || '');
        html += `
          <div class="grp-row">
            <label>
              <input type="checkbox" class="chkGroupOrder" value="${goid}" data-name="${escapeHtml(name)}" />
              <span>${escapeHtml(name)}</span>
              <span class="muted">(${grpCount} grup)</span>
            </label>
          </div>`;
      }
    } else {
      html = `<div class="grp-row"><span class="muted">Belum ada grup order.</span></div>`;
    }
    list.innerHTML = html;

    // Wiring: Group order checkbox → uncheck group checkboxes & vice versa
    document.querySelectorAll('.chkGroupOrder').forEach(cb => {
      cb.addEventListener('change', () => {
        if (cb.checked) {
          // Jika group order dipilih, uncheck semua grup
          document.querySelectorAll('.chkGroup').forEach(x => x.checked = false);
        }
        renderGroupSummary();
      });
    });
  } catch(e) {
    console.error('loadGroupOrders error', e);
  }
}

async function loadGroups(){
  try {
    const res = await fetch('?ajax=groups', {cache:'no-store'});
    const json = await res.json();
    const list = document.getElementById('groupList');
    if (!list) return;

    let html = `
      <div class="grp-row">
      </div>
    `;

    if (json.ok && Array.isArray(json.data) && json.data.length > 0) {
      for (const g of json.data) {
        const gid = parseInt(g.id,10) || 0;
        const mem = parseInt(g.members,10) || 0;
        const name = String(g.name || '');
        const checked = currentGroupIds.has(gid) ? 'checked' : '';
        html += `
          <div class="grp-row">
            <label>
              <input type="checkbox" class="chkGroup" value="${gid}" data-name="${escapeHtml(name)}" ${checked} />
              <span>${escapeHtml(name)}</span>
              <span class="muted">(${mem})</span>
            </label>
          </div>`;
      }
    } else {
      html += `<div class="grp-row"><span class="muted">Belum ada grup.</span></div>`;
    }
    list.innerHTML = html;

    // Wiring: Grup checkbox → uncheck group orders
    document.querySelectorAll('.chkGroup').forEach(cb => {
      cb.addEventListener('change', () => {
        if (cb.checked) {
          // Jika grup dipilih, uncheck semua group orders
          document.querySelectorAll('.chkGroupOrder').forEach(x => x.checked = false);
        }
        renderGroupSummary();
      });
    });

    // Wiring: "Semua Kontak" - untuk select semua kontak manual
    const chkAll = document.getElementById('chkAllContacts');
    if (chkAll){
      chkAll.addEventListener('change', () => {
        if (chkAll.checked){
          // Uncheck semua group & group order checkbox
          document.querySelectorAll('.chkGroup').forEach(cb => cb.checked = false);
          document.querySelectorAll('.chkGroupOrder').forEach(cb => cb.checked = false);
          // Ini trigger reload untuk "semua kontak" mode
          currentGroupIds = new Set();
          renderGroupSummary();
          loadContactsMulti(new Set());
        }
      });
    }
    // Checkbox grup → uncheck "Semua" jika ada yang dipilih
    document.querySelectorAll('.chkGroup').forEach(cb => {
      cb.addEventListener('change', () => {
        const anyChecked = [...document.querySelectorAll('.chkGroup')].some(x => x.checked);
        if (chkAll) chkAll.checked = !anyChecked;
      });
    });

    // Sync badge (tetap)
    renderGroupSummary();
  } catch(e) {
    console.error('loadGroups error', e);
  }
}

async function loadContactsMulti(groupIdsSet = new Set()){
  try {
    const ids = [...groupIdsSet].filter(n => Number.isInteger(n) && n > 0);
    const qs = ids.length ? `group_ids=${encodeURIComponent(ids.join(','))}` : '';
    const url = qs ? `?ajax=contacts&${qs}` : `?ajax=contacts`;
    const res = await fetch(url, {cache:'no-store'});
    const json = await res.json();
    if (!(json.ok && Array.isArray(json.data))) return;

    if (groupIdsSet.size > 0) {
      latestGroupedContacts = json.data;
      renderContacts(json.data, /*byGroup*/true);
      setTotalCount(json.data.length);
      updateSelectedCount();
      syncHiddenRecipients();
      return;
    }

    latestGroupedContacts = [];
    renderContacts(json.data, /*byGroup*/false);
    setTotalCount(json.data.length);
    filterContacts();
    updateSelectedCount();
    syncHiddenRecipients();
  } catch(e) {
    console.error('loadContactsMulti error', e);
  }
}

async function loadContactsForCC(){
  try {
    const res = await fetch('?ajax=contacts', {cache:'no-store'});
    const json = await res.json();
    if (!(json.ok && Array.isArray(json.data))) return;

    renderContactsCC(json.data);
    setTotalCount(json.data.length);
    filterContacts();
  } catch(e) {
    console.error('loadContactsForCC error', e);
  }
}

async function loadCCGroupsForDrawer(){
  const container = document.getElementById('ccGroupBtns');
  if (!container) return;
  try {
    const res = await fetch('?ajax=cc_groups', {cache:'no-store'});
    const json = await res.json();
    if (!json.ok || !Array.isArray(json.data) || json.data.length === 0) {
      container.innerHTML = '<span style="color:#888;font-size:0.85rem;">Belum ada grup CC. Buat di halaman <a href="contacts.php" target="_blank">Kontak</a>.</span>';
      return;
    }
    let html = '';
    for (const g of json.data) {
      const emails = JSON.stringify(g.emails || []);
      html += `<button type="button" class="btn" style="font-size:0.8rem;padding:4px 10px;background:#DCEDC8;color:#33691E;border-color:#AED581;"
        onclick="applyCCGroup(${g.id}, ${escapeHtml(JSON.stringify(g.name))}, ${escapeHtml(emails)})">
        📋 ${escapeHtml(String(g.name))} <span style="opacity:0.7;">(${g.members})</span>
      </button>`;
    }
    container.innerHTML = html;
  } catch(e) {
    console.error('loadCCGroupsForDrawer error', e);
    container.innerHTML = '<span style="color:#888;font-size:0.85rem;">Gagal memuat grup CC.</span>';
  }
}

function applyCCGroup(groupId, groupName, emails){
  if (!Array.isArray(emails) || emails.length === 0) {
    alert('Grup CC "' + groupName + '" belum memiliki anggota.');
    return;
  }
  let added = 0;
  emails.forEach(email => {
    if (email && !selectedCCSet.has(email)) {
      selectedCCSet.add(email);
      added++;
    }
  });
  // Re-render CC contact list to reflect new selections
  loadContactsForCC().then(() => {
    filterContacts();
  });
  // Update counter
  const ccCountEl = document.getElementById('selectedCCCount');
  if (ccCountEl) ccCountEl.textContent = selectedCCSet.size;
  // Flash feedback on the button
  const btn = event?.target?.closest('button');
  if (btn) {
    const orig = btn.style.background;
    btn.style.background = '#8BC34A';
    btn.textContent = `✓ ${groupName} ditambahkan`;
    setTimeout(() => { btn.style.background = orig; loadCCGroupsForDrawer(); }, 1500);
  }
}

// ======= Renderers & filtering =======
function renderContacts(items, byGroup){
  const tbody = document.getElementById('contactTbody');
  if (!tbody) return;

  const theadCb = document.getElementById('thCheckbox');
  if (theadCb) theadCb.style.display = byGroup ? 'none' : '';

  let html = '';
  for (const c of items) {
    const name = String(c.display_name || '').trim();
    const email = String(c.email || '').trim();
    const source = String(c.source || '');
    const title = (name ? (name + ' <' + email + '>') : email);
    const val = `${email}|${name}`;

    if (byGroup) {
      html += `
        <tr data-name="${escapeHtml(name.toLowerCase())}" data-email="${escapeHtml(email.toLowerCase())}">
          <td><!-- kosong untuk align --></td>
          <td title="${escapeHtml(title)}">${escapeHtml(name || '(tanpa nama)')}</td>
          <td>${escapeHtml(email)}</td>
          <td>${escapeHtml(source)}</td>
        </tr>`;
    } else {
      const checked = selectedSet.has(val) ? ' checked' : '';
      html += `
        <tr data-name="${escapeHtml(name.toLowerCase())}" data-email="${escapeHtml(email.toLowerCase())}">
          <td><input type="checkbox" name="recipients[]" value="${escapeHtml(val)}"${checked}></td>
          <td title="${escapeHtml(title)}">${escapeHtml(name || '(tanpa nama)')}</td>
          <td>${escapeHtml(email)}</td>
          <td>${escapeHtml(source)}</td>
        </tr>`;
    }
  }
  tbody.innerHTML = html;

  // Hint mode
  const hint = document.getElementById('groupModeHint');
  if (hint) {
    hint.style.display = byGroup ? '' : 'none';
    if (byGroup) {
      const n = items.length;
      hint.innerHTML = `Mode <strong>by Grup</strong> aktif — <strong>${n}</strong> penerima otomatis diambil dari gabungan grup terpilih.`;
    }
  }
}

function renderContactsCC(items){
  const tbody = document.getElementById('contactTbody');
  if (!tbody) return;

  const theadCb = document.getElementById('thCheckbox');
  if (theadCb) theadCb.style.display = '';

  let html = '';
  for (const c of items) {
    const name = String(c.display_name || '').trim();
    const email = String(c.email || '').trim();
    const source = String(c.source || '');
    const title = (name ? (name + ' <' + email + '>') : email);

    const checked = selectedCCSet.has(email) ? ' checked' : '';
    html += `
      <tr data-name="${escapeHtml(name.toLowerCase())}" data-email="${escapeHtml(email.toLowerCase())}">
        <td><input type="checkbox" class="ccEmail" value="${escapeHtml(email)}"${checked}></td>
        <td title="${escapeHtml(title)}">${escapeHtml(name || '(tanpa nama)')}</td>
        <td>${escapeHtml(email)}</td>
        <td>${escapeHtml(source)}</td>
      </tr>`;
  }
  tbody.innerHTML = html;

  // Hide hint untuk CC mode
  const hint = document.getElementById('groupModeHint');
  if (hint) hint.style.display = 'none';
}

function filterContacts(){
  const input = document.getElementById('contactSearch');
  const q = (input?.value || '').toLowerCase();
  const rows = document.querySelectorAll('#contactTable tbody tr');
  rows.forEach(tr => {
    const name = tr.getAttribute('data-name') || '';
    const email = tr.getAttribute('data-email') || '';
    const hit = (q === '' || name.includes(q) || email.includes(q));
    tr.style.display = hit ? '' : 'none';
  });
  updateVisibleCount();
}

function selectAllFiltered(mark=true){
  if (currentGroupIds.size > 0) return; // by group -> disable
  const rows = document.querySelectorAll('#contactTable tbody tr');
  rows.forEach(tr => {
    if (tr.style.display !== 'none') {
      const cb = tr.querySelector('input[type=checkbox][name="recipients[]"]');
      if (cb) {
        cb.checked = !!mark;
        const val = cb.value;
        if (cb.checked) selectedSet.add(val); else selectedSet.delete(val);
      }
    }
  });
  updateSelectedCount();
  syncHiddenRecipients();
}

function clearAllSelection(){
  if (currentGroupIds.size > 0) return;
  document.querySelectorAll('input[type=checkbox][name="recipients[]"]').forEach(cb => cb.checked = false);
  selectedSet.clear();
  updateSelectedCount();
  syncHiddenRecipients();
}

// ======= Delivery Date & Subject Auto-Generation =======
function formatDateToIndonesia(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr + 'T00:00:00');
  const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  
  const dayName = days[date.getDay()];
  const day = String(date.getDate()).padStart(2, '0');
  const monthName = months[date.getMonth()];
  const year = date.getFullYear();
  
  return `${dayName}, ${day} ${monthName} ${year}`;
}

function updateSubjectWithDeliveryDate() {
  const subjectField = document.getElementById('subjectField');
  const deliveryDateField = document.getElementById('deliveryDate');
  const orderConsumableModeInput = document.getElementById('orderConsumableModeInput');
  
  if (!subjectField) return;
  
  // Jika PO MTC aktif, jangan override subject
  if (poMtcData) return;
  
  // Mode khusus ORDER CONSUMABLE: subject selalu dimulai dari DN ORDER CONSUMABLE
  if (isOrderConsumableTemplate) {
    const baseSubject = 'DN ORDER CONSUMABLE';
    subjectField.setAttribute('data-base-subject', baseSubject);
    if (orderConsumableModeInput) orderConsumableModeInput.value = '1';

    const deliveryDate = deliveryDateField?.value || '';
    if (deliveryDate) {
      const formattedDate = formatDateToIndonesia(deliveryDate);
      subjectField.value = `${baseSubject} ${formattedDate}`;
    } else {
      subjectField.value = baseSubject;
    }
    return;
  }

  if (orderConsumableModeInput) orderConsumableModeInput.value = '0';

  const baseSubject = subjectField.getAttribute('data-base-subject') || subjectField.value.split(' (')[0];
  subjectField.setAttribute('data-base-subject', baseSubject);
  
  const deliveryDate = deliveryDateField?.value || '';
  
  if (deliveryDate && currentGroupName) {
    const formattedDate = formatDateToIndonesia(deliveryDate);
    subjectField.value = `${baseSubject} ${formattedDate} (${currentGroupName})`;
  } else {
    subjectField.value = baseSubject;
  }
}

// ======= PO BULANAN CONSUMABLE: Insert Month/Year into body =======
function insertConsumableMonthYear() {
  if (typeof quill === 'undefined') return;
  const deliveryDateField = document.getElementById('deliveryDate');
  if (!deliveryDateField || !deliveryDateField.value) return;

  const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  const date = new Date(deliveryDateField.value + 'T00:00:00');
  const monthYear = `Bulan ${months[date.getMonth()]} ${date.getFullYear()}`;

  // Search for "PO CONSUMABLE ADM KAP" in plain text of Quill
  const text = quill.getText();
  const needle = 'PO CONSUMABLE ADM KAP';
  const idx = text.indexOf(needle);
  if (idx === -1) return;

  const afterIdx = idx + needle.length;

  // Check if month/year is already inserted (avoid duplicates)
  const textAfter = text.substring(afterIdx, afterIdx + 50);
  if (textAfter.match(/^\s*Bulan\s+\w+\s+\d{4}/)) {
    // Already has month/year - replace it
    const oldMatch = textAfter.match(/^(\s*)Bulan\s+\w+\s+\d{4}/);
    if (oldMatch) {
      quill.deleteText(afterIdx, oldMatch[0].length);
      quill.insertText(afterIdx, ' ' + monthYear);
    }
  } else {
    // Insert month/year after "PO CONSUMABLE ADM KAP"
    quill.insertText(afterIdx, ' ' + monthYear);
  }
  console.log('Consumable month/year inserted:', monthYear);
}

// ======= PO MTC Auto-Subject/Body =======
async function applyPoMtcLogic(groupOrderId, groupOrderName) {
  // Cek apakah nama group order = "PO MTC"
  if (groupOrderName.trim().toUpperCase() !== 'PO MTC') {
    poMtcData = null;
    document.getElementById('poMtcDataInput').value = '';
    return;
  }

  try {
    const res = await fetch(`?ajax=group_order_grouped&group_order_id=${groupOrderId}`, {cache:'no-store'});
    const json = await res.json();
    if (!json.ok) return;

    const groups = json.groups || [];
    const attachments = json.attachments || [];

    // Build per-group data: subject, body snippet, matched attachments
    const perGroupData = [];
    for (const g of groups) {
      const groupName = g.group_name || '';

      // Find matching attachments for this group (by group name similarity)
      const matchedFiles = [];
      const groupNameLower = groupName.toLowerCase().replace(/[^a-z0-9]/g, '');
      for (const att of attachments) {
        const filenameLower = att.filename.toLowerCase().replace(/[^a-z0-9]/g, '');
        // Check if attachment filename contains group name or vice versa
        if (groupNameLower && (filenameLower.includes(groupNameLower) || groupNameLower.includes(filenameLower))) {
          matchedFiles.push(att);
        }
      }

      // Extract PO numbers from matched attachment filenames
      // PO number pattern: digits of 10+ chars (e.g., 4100129502)
      const poNumbers = [];
      for (const att of matchedFiles) {
        const matches = att.filename.match(/\d{7,}/g);
        if (matches) {
          for (const m of matches) poNumbers.push(m);
        }
      }

      perGroupData.push({
        group_id: g.group_id,
        group_name: groupName,
        subject: `PURCHASE ORDER ADM KAP - (${groupName})`,
        supplier_name: groupName,
        po_numbers: poNumbers,
        matched_files: matchedFiles.map(f => f.filename),
        contacts: g.contacts,
      });
    }

    poMtcData = perGroupData;
    // Pass to hidden field for match_preview
    document.getElementById('poMtcDataInput').value = JSON.stringify(perGroupData);

    // Set subject to template (will be overridden per-group in match_preview)
    const subjectField = document.getElementById('subjectField');
    if (subjectField) {
      subjectField.value = 'PURCHASE ORDER ADM KAP';
      subjectField.setAttribute('data-base-subject', 'PURCHASE ORDER ADM KAP');
    }

    // Auto-generate body template if body is empty
    if (typeof quill !== 'undefined') {
      const currentBody = quill.root.innerHTML.trim();
      if (!currentBody || currentBody === '<p><br></p>' || currentBody === '<p></p>') {
        quill.root.innerHTML = `<p>Dear ,</p><p><br></p><p>Selamat Siang,</p><p><br></p><p>Bersama email ini kami kirimkan Purchase Order ADM KAP</p><p>Dengan Nomor :</p><p><br></p><p>Mohon konfirmasi penerimaan PO ini.</p><p><br></p><p>Terima kasih.</p>`;
      }
    }

    console.log('PO MTC data prepared:', perGroupData.length, 'groups');
  } catch(e) {
    console.error('Error in applyPoMtcLogic:', e);
  }
}

// ======= Dropdown (filter grup) UI =======
function toggleGroupDropdown(){
  const panel = document.getElementById('groupDropdownPanel');
  panel?.classList.toggle('open');
}
function closeGroupDropdown(){
  const panel = document.getElementById('groupDropdownPanel');
  panel?.classList.remove('open');
}

// ======= DOMContentLoaded wiring =======
document.addEventListener('DOMContentLoaded', () => {
  // Event delegation untuk ceklis manual recipients & CC
  const tbody = document.getElementById('contactTbody');
  if (tbody){
    tbody.addEventListener('change', (e) => {
      const t = e.target;
      
      // Handle recipients checkbox
      if (t && t.matches('input[type=checkbox][name="recipients[]"]')) {
        const val = t.value;
        if (t.checked) selectedSet.add(val); else selectedSet.delete(val);
        updateSelectedCount();
        syncHiddenRecipients();
      }
      
      // Handle CC checkbox
      if (t && t.matches('input.ccEmail')) {
        const email = t.value;
        if (t.checked) {
          selectedCCSet.add(email);
        } else {
          selectedCCSet.delete(email);
        }
        // Update CC count display di drawer
        const ccCountEl = document.getElementById('selectedCCCount');
        if (ccCountEl) {
          ccCountEl.textContent = selectedCCSet.size;
        }
        // Debug log
        console.log('CC Selection updated:', t.checked ? '✓ Added' : '✗ Removed', email, '| Total:', selectedCCSet.size);
      }
    });
  }

  // Search input live filter
  const inp = document.getElementById('contactSearch');
  if (inp){ inp.addEventListener('input', filterContacts); }

  // Delivery date change - auto update subject
  const deliveryDateField = document.getElementById('deliveryDate');
  if (deliveryDateField) {
    deliveryDateField.addEventListener('change', () => {
      updateSubjectWithDeliveryDate();
      insertConsumableMonthYear();
    });
  }

  // Dropdown toggler
  document.getElementById('groupDropdownBtn')?.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleGroupDropdown();
    if (document.getElementById('groupDropdownPanel')?.classList.contains('open')) {
      loadGroupOrders();
      loadGroups();
    }
  });

  // Apply pilihan grup
  document.getElementById('btnGroupsApply')?.addEventListener('click', async () => {
    if (drawerMode === 'cc') {
      // CC Mode: gabungkan pilihan CC dan masukkan ke field CC
      const ccField = document.querySelector('input[name="cc"]');
      if (ccField && selectedCCSet.size > 0) {
        // Ambil emails dari selectedCCSet
        const selectedEmails = Array.from(selectedCCSet);
        
        // Split email yang sudah ada di field CC
        const currentCCs = ccField.value.trim().split(';').map(s => s.trim()).filter(s => s);
        
        // Gabungkan dan deduplicate
        const allCCs = [...new Set([...currentCCs, ...selectedEmails])];
        
        // Set value field CC dengan format "email1; email2; email3"
        ccField.value = allCCs.join('; ');
        
        console.log('CC updated:', ccField.value);
      } else if (ccField && selectedCCSet.size === 0) {
        alert('Pilih minimal satu email untuk CC');
        return;
      }
      closeDrawer();
      return;
    }
    
    // Recipients Mode: check group order atau group selection
    
    // Check apakah ada group order yang dipilih
    const groupOrderChecks = document.querySelectorAll('.chkGroupOrder');
    const groupOrderIds = [];
    const groupOrderNames = new Map();
    [...groupOrderChecks].forEach(cb => {
      if (cb.checked) {
        const goid = parseInt(cb.value,10);
        const name = cb.getAttribute('data-name') || '';
        if (Number.isInteger(goid) && goid > 0) {
          groupOrderIds.push(goid);
          groupOrderNames.set(goid, name);
        }
      }
    });

    // Jika ada group order dipilih, load contacts dari group order
    if (groupOrderIds.length > 0) {
      // Fetch contacts dan actual group IDs dari group order
      try {
        const groupOrderId = groupOrderIds[0]; // For now, hanya support 1 group order
        const res = await fetch(`?ajax=group_order_contacts&group_order_id=${groupOrderId}`, {cache:'no-store'});
        const json = await res.json();
        
        if (json.ok && Array.isArray(json.data)) {
          // Gunakan actual group IDs (bukan group order ID) untuk proper grouping di match_preview
          const actualGroupIds = json.group_ids || [];
          currentGroupIds = new Set(actualGroupIds);  // Store actual GROUP IDs, not order IDs!
          
          // Store group order name untuk display
          currentGroupNames.clear();
          groupOrderNames.forEach((name, goid) => {
            if (groupOrderIds.includes(goid)) {
              currentGroupNames.set(goid, name);
            }
          });
          
          // Set group name untuk subject generation (ambil nama group order)
          currentGroupName = groupOrderNames.get(groupOrderIds[0]) || '';
          
          latestGroupedContacts = json.data;
          renderContacts(json.data, /*byGroup*/true);
          setTotalCount(json.data.length);
          updateSelectedCount();
          syncHiddenRecipients();

          renderGroupSummary();
          closeGroupDropdown();
          
          // Update subject jika delivery date sudah dipilih
          updateSubjectWithDeliveryDate();
          
          // PO MTC auto-subject/body logic
          await applyPoMtcLogic(groupOrderId, groupOrderNames.get(groupOrderIds[0]) || '');
          
          // Load template untuk group order yang dipilih
          // Priority: Try GROUP ORDER first (yang baru di-link), fallback to GROUP IDs
          await loadTemplateForSelection(actualGroupIds.length > 0 ? actualGroupIds[0] : null, groupOrderId);
        }
      } catch(e) {
        console.error('Error loading group order contacts:', e);
      }
    } else {
      // Check apakah ada group yang dipilih
      const checks = document.querySelectorAll('.chkGroup');
      const ids = [];
      const names = new Map();
      [...checks].forEach(cb => {
        if (cb.checked) {
          const gid = parseInt(cb.value,10);
          const name = cb.getAttribute('data-name') || '';
          if (Number.isInteger(gid) && gid > 0) {
            ids.push(gid);
            names.set(gid, name);
          }
        }
      });
      currentGroupIds = new Set(ids);
      currentGroupNames = names;
      
      // Set group name untuk subject generation (ambil nama group pertama)
      currentGroupName = ids.length > 0 ? (names.get(ids[0]) || '') : '';

      // Reset PO MTC data when using individual groups
      poMtcData = null;
      document.getElementById('poMtcDataInput').value = '';
      
      loadContactsMulti(currentGroupIds);
      
      renderGroupSummary();
      closeGroupDropdown();

      // Update subject jika delivery date sudah dipilih
      updateSubjectWithDeliveryDate();

      // Load template untuk group yang dipilih (ambil group pertama)
      if (ids.length > 0) {
        await loadTemplateForSelection(ids[0], null);
      }
    }

  });

  // Select all groups (di panel)
  document.getElementById('btnGroupsSelectAll')?.addEventListener('click', () => {
    if (drawerMode === 'cc') {
      // CC mode: select all CC emails
      document.querySelectorAll('input.ccEmail').forEach(cb => cb.checked = true);
      selectedCCSet.clear();
      document.querySelectorAll('input.ccEmail').forEach(cb => selectedCCSet.add(cb.value));
    } else {
      // Recipients mode: select all groups & group orders
      document.getElementById('chkAllContacts')?.setAttribute('checked', 'false');
      document.querySelectorAll('.chkGroup').forEach(cb => cb.checked = true);
      document.querySelectorAll('.chkGroupOrder').forEach(cb => cb.checked = true);
    }
  });

  // Clear groups (di panel)
  document.getElementById('btnGroupsClear')?.addEventListener('click', () => {
    if (drawerMode === 'cc') {
      // CC mode: deselect all CC emails
      document.querySelectorAll('input.ccEmail').forEach(cb => cb.checked = false);
      selectedCCSet.clear();
    } else {
      // Recipients mode: clear groups & group orders
      document.querySelectorAll('.chkGroup').forEach(cb => cb.checked = false);
      document.querySelectorAll('.chkGroupOrder').forEach(cb => cb.checked = false);
      const chkAll = document.getElementById('chkAllContacts');
      if (chkAll) chkAll.checked = true;
    }
  });

  // Refresh daftar grup (di panel)
  document.getElementById('btnGroupsRefresh')?.addEventListener('click', () => {
    loadGroupOrders();
    loadGroups();
  });

  // Load template untuk group/group order yang dipilih
  async function loadTemplateForSelection(groupId, groupOrderId) {
    try {
      let url;
      // Priority: GROUP ORDER first, then GROUP
      if (groupOrderId && groupOrderId > 0) {
        url = `?ajax=get_template_by_group_order&group_order_id=${groupOrderId}`;
        console.log('🔍 Trying to load template by GROUP ORDER ID:', groupOrderId);
      } else if (groupId && groupId > 0) {
        url = `?ajax=get_template_by_group&group_id=${groupId}`;
        console.log('🔍 Trying to load template by GROUP ID:', groupId);
      } else {
        // No group/group_order selected, don't load template
        console.log('ℹ️ No group/group order selected');
        return;
      }

      const res = await fetch(url, {cache:'no-store'});
      const json = await res.json();
      
      console.log('📥 Template loading response:', json);
      
      if (json.ok && json.data && json.data.body) {
        const templateData = json.data;
        const templateName = templateData.name || 'Template';
        const templateDesc = templateData.description || '';
        const templateBody = templateData.body;
        
        console.log('📋 Template data:', { name: templateName, desc: templateDesc, bodyLength: templateBody.length });
        
        // Tampilkan preview modal dan tunggu user response
        const result = await Swal.fire({
          title: `📋 Preview Template: ${templateName}`,
          html: `
            <div style="text-align: left; margin: 16px 0;">
              ${templateDesc ? `<p style="color: #666; font-size: 13px; margin-bottom: 12px;"><strong>Deskripsi:</strong> ${templateDesc}</p>` : ''}
              <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; max-height: 300px; overflow-y: auto; font-size: 13px;">
                ${templateBody}
              </div>
              <p style="margin-top: 12px; color: #999; font-size: 12px;">✓ Anda masih bisa mengedit template setelah memuat</p>
            </div>
          `,
          icon: 'info',
          showCancelButton: true,
          confirmButtonText: '✅ Gunakan Template',
          cancelButtonText: '❌ Batal',
          width: '600px'
        });
        
        // Process result setelah user menutup modal
        if (result.isConfirmed) {
          console.log('✅ User confirmed loading template');
          // Load template ke Quill editor
          quill.setContents(quill.clipboard.convert({html: templateBody}));

          // Aktifkan mode subject khusus untuk template ORDER CONSUMABLE
          isOrderConsumableTemplate = /order\s*consumable/i.test(templateName);
          if (isOrderConsumableTemplate) {
            const subjectField = document.getElementById('subjectField');
            if (subjectField) {
              subjectField.setAttribute('data-base-subject', 'DN ORDER CONSUMABLE');
            }
          }
          updateSubjectWithDeliveryDate();
          
          // Insert month/year for PO BULANAN CONSUMABLE template
          insertConsumableMonthYear();
          
          // Auto-close drawer
          closeDrawer();
          console.log('🚪 Drawer auto-closed');
          
          // Show success notification
          await Swal.fire({
            title: 'Template Dimuat',
            text: `Template "${templateName}" telah dimuat ke body email. Anda masih bisa mengeditnya!`,
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
          });
          console.log('✨ Template successfully loaded to editor');
        } else {
          console.log('❌ User cancelled template loading');
        }
      } else {
        console.log('ℹ️ No template found or invalid response');
      }
    } catch(e) {
      console.error('❌ Error loading template:', e);
      Swal.fire('Error', 'Gagal memuat template: ' + e.message, 'error');
    }
  }

  // Clear/Reset template body
  function clearTemplate() {
    Swal.fire({
      title: 'Hapus Template?',
      text: 'Konten email akan dihapus. Anda tidak bisa undo!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: '✅ Hapus',
      cancelButtonText: '❌ Batal',
      confirmButtonColor: '#dc2626'
    }).then((result) => {
      if (result.isConfirmed) {
        quill.setContents([]);
        Swal.fire('Terhapus', 'Konten email berhasil dihapus', 'success');
      }
    });
  }

  // Klik di luar → tutup dropdown
  document.addEventListener('click', (e) => {
    const dp = document.getElementById('groupDropdownPanel');
    const btn = document.getElementById('groupDropdownBtn');
    if (!dp || !btn) return;
    if (!dp.contains(e.target) && !btn.contains(e.target)) closeGroupDropdown();
  });

  // Hapus grup dari badge (klik ×)
  document.getElementById('groupBadges')?.addEventListener('click', (e) => {
    const x = e.target;
    if (x && x.classList.contains('x')) {
      const chip = x.closest('.chip');
      const gid = parseInt(chip?.getAttribute('data-gid') || '0', 10);
      if (gid > 0) {
        currentGroupIds.delete(gid);
        currentGroupNames.delete(gid);
        renderGroupSummary();
        loadContactsMulti(currentGroupIds);
        // Jika dropdown terbuka, refresh state checkbox
        if (document.getElementById('groupDropdownPanel')?.classList.contains('open')) {
          loadGroups();
        }
      }
    }
  });

  // ESC to close dropdown & drawer
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape'){
      closeGroupDropdown();
      closeDrawer();
    }
  });

  // Click on overlay background (not drawer) to close drawer
  document.getElementById('drawerOverlay')?.addEventListener('click', (e) => {
    if (e.target.id === 'drawerOverlay') {
      closeDrawer();
    }
  });

  // Initial load (mode manual)
  renderGroupSummary();
  loadContactsMulti(new Set());
  updateSelectedCount();
  syncHiddenRecipients();

  // Clear all attachments
  window.clearAllAttachments = async function() {
    const result = await Swal.fire({
      title: 'Hapus Semua Lampiran',
      text: 'Apakah Anda yakin ingin menghapus SEMUA lampiran?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus semua',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#6b7280'
    });
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'compose.php';
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'clear_attachment';
      input.value = '1';
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }
  };

  // Form submit validation untuk cek attachment (dari database via hidden input)
  const composeForm = document.getElementById('composeForm');
  if (composeForm) {
    composeForm.addEventListener('submit', async (e) => {
      const hasAttachments = document.getElementById('hasAttachments').value === '1';
      if (!hasAttachments) {
        e.preventDefault();
        const result = await Swal.fire({
          title: 'Tidak ada lampiran',
          text: 'Apakah Anda ingin menambahkan lampiran terlebih dahulu atau melanjutkan tanpa lampiran?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Lanjut tanpa lampiran',
          cancelButtonText: 'Tambah lampiran dulu',
          confirmButtonColor: '#16a34a',
          cancelButtonColor: '#f59e0b'
        });
        if (result.isConfirmed) {
          // User confirm untuk lanjut tanpa lampiran
          composeForm.submit();
        } else if (result.isDismissed) {
          // User cancel - scroll ke upload section
          const uploadSection = document.querySelector('h3');
          if (uploadSection) {
            uploadSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }
      }
    });
  }
});
</script>
<!-- Quill Rich Text Editor -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.snow.css?v=2.0" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.js?v=2.0"></script>
</head>
<body>
<header>
  <h2 style="margin:0 0 8px 0;">Compose & Upload</h2>
  <div class="tools">
    <a class="btn" href="index.php">⟵ Dashboard</a>
  </div>
  <div style="margin-top:12px;padding:8px 12px;background:rgba(255,255,255,0.15);border-radius:6px;font-size:13px;">
    <strong>📧 Pengirim:</strong> <?= e(get_sender_account()) ?>
  </div>
</header>

<main>
  <!-- Separate upload form that only handles files -->
  <form method="post" action="compose.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
  <div class="card card-collapsible collapsed" id="cardUpload">
    <h3 class="slider-toggle" onclick="toggleCardSlider('cardUpload')">
      <span>1) Upload Lampiran (Opsional)</span>
      <span class="slider-hint">Klik untuk membuka</span>
      <span class="toggle-icon">▼</span>
    </h3>
    <div class="card-slider">
    <table style="width:100%;border-collapse:collapse;">
      <tr>
        <td style="vertical-align:top; border-right:1px solid #2a2b2d;">
          <div class="body">
            <label style="display:block;margin-bottom:8px;">
              <strong>Pilih satu atau lebih file untuk diunggah:</strong>
              <input type="file" name="files[]" multiple accept=".pdf,.xlsx,.xls,.doc,.docx,.csv,.zip,.jpg,.jpeg,.png,.txt" style="display:block;margin-top:6px;">
            </label>
            <button type="submit" class="btn" style="margin-top:8px;">⬆️ Upload File Sekarang</button>
            <?php if ($uploadMsg): ?>
              <pre style="margin-top:12px;background:#f0f2f5;padding:10px;border-radius:6px;white-space:pre-wrap;font-size:12px;max-height:5rem;overflow-y:auto;"><?= e($uploadMsg) ?></pre>
            <?php endif; ?>
            <p class="small" style="margin-top:8px;">
              <strong>Format yang diperbolehkan:</strong> PDF, Excel (xlsx/xls), Word (doc/docx), CSV, ZIP, JPG/PNG, TXT<br>
              <strong>Dapat mengunggah multiple file sekaligus!</strong> 📁
            </p>
          </div>
        </td>
        <td style="vertical-align:top;">
          <h3>Lampiran Tersedia</h3>
          <div class="body" style="max-height:300px;overflow:auto;">
            <div style="text-align:right; margin-bottom:8px;">
              <button type="button" class="btn" style="padding:7px 7px;font-size:11px;background:#dc2626;cursor:pointer;" onclick="clearAllAttachments()">🗑️ Clear All Attachments</button>
            </div>
            <?php if (empty($attachments)): ?>
              <p class="small"><em>Belum ada lampiran yang diunggah.</em></p>
            <?php else: ?>
              <table class="table">
                <thead>
                  <tr><th>File</th><th>Ukuran</th><th>Diunggah</th><th style="width:80px;">Aksi</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($attachments as $a): ?>
                    <tr style="font-size:8pt;">
                      <td><?= e($a['filename']) ?></td>
                      <td><?= e(human_filesize((int)$a['size'])) ?></td>
                      <td><?= e($a['uploaded_at']) ?></td>
                      <td style="text-align:center;">
                        <form method="post" action="compose.php" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                          <button type="submit" name="delete_attachment" value="<?= (int)$a['id'] ?>" class="btn secondary" style="padding:4px 8px;font-size:11px;background:#dc2626;cursor:pointer;" onclick="return confirm('Hapus file ini?');">🗑️ Hapus</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <p class="small" style="margin-top:8px;">
                <strong>Catatan:</strong> Sistem akan mencocokkan nama file lampiran dengan nama/email penerima menggunakan kemiripan string.
              </p>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    </table>
    </div>
  </div>
  </form>
  <!-- /Upload form -->

  <!-- Main email compose form -->
  <form method="post" action="match_preview.php" id="composeForm">
    <!-- CSRF token -->
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <!-- Hidden recipients sinkron setiap perubahan -->
    <div id="hiddenRecipients"></div>
    <!-- Hidden attachment count from database -->
    <input type="hidden" id="hasAttachments" value="<?= $hasAttachments ? '1' : '0' ?>">
    <!-- Hidden selected group IDs (untuk tracking group membership di send) -->
    <input type="hidden" id="selectedGroupIds" name="selected_group_ids" value="">
    <!-- Hidden PO MTC per-group data -->
    <input type="hidden" id="poMtcDataInput" name="po_mtc_data" value="">
    <!-- Hidden mode flag untuk subject ORDER CONSUMABLE -->
    <input type="hidden" id="orderConsumableModeInput" name="order_consumable_mode" value="0">

    <div class="card card-collapsible" id="cardIsiEmail">
      <h3 class="slider-toggle" onclick="toggleCardSlider('cardIsiEmail')">
        <span>2) Isi Email</span>
        <span class="slider-hint">Klik untuk membuka</span>
        <span class="toggle-icon">▼</span>
      </h3>
      <div class="card-slider">
      <div class="body">
        <!-- Trigger open contacts slider -->
        <button type="button" class="btn" onclick="openDrawer()">
          📇 Buka Kontak (Slider)
          <span class="badge"><span id="selectedCount">0</span> / <span id="totalCount"><?= (int)$totalContacts ?></span></span>
        </button>
        <div id="groupModeHint" class="hint" style="display:none;margin-bottom:8px;"></div>
        <label>Subject
          <input class="input full" type="text" name="subject" id="subjectField" required placeholder="Judul email (wajib untuk kirim)">
        </label>
        <label>📅 Tanggal Delivery (Optional - akan ditambahkan ke subject)
          <input class="input full" type="date" id="deliveryDate" placeholder="Pilih tanggal delivery">
          <div class="small" style="margin-top: 4px; color: #666;">💡 Jika diisi, format subject akan otomatis menjadi: <strong>"[Subject] [Hari], [Tanggal Bulan Tahun] ([Nama Grup])"</strong></div>
        </label>
        <label>CC
          <div style="display:flex;gap:8px;align-items:flex-start;">
            <input class="input" type="text" name="cc" id="ccField" placeholder="Pisahkan dengan ;" style="flex:1;">
            <button type="button" class="btn" onclick="openDrawer('cc')" style="white-space:nowrap;margin-top:0;">📇 Pilih</button>
          </div>
        </label>
        <label>Body (Gambar & Format HTML diperbolehkan)
          <div style="display:flex;gap:8px;margin-bottom:8px;">
            <button type="button" class="btn danger" onclick="clearTemplate()" style="white-space:nowrap;margin-top:0;">🗑️ Hapus Template</button>
          </div>
          <div id="editorContainer" style="background:#fff;border:1px solid #d1d5db;border-radius:6px;height:300px;"></div>
          <input type="hidden" name="body" id="bodyInput" value="">
        </label>
        <label>Ambang kemiripan (0–100)
          <input class="input" type="number" name="threshold" min="0" max="100" value="90" style="width:6%;color:black;font-weight:600;">
        </label>

        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;">
          <input type="checkbox" name="use_ai_matching" id="useAIMatching" value="1" style="width:18px;height:18px;cursor:pointer;">
          <span style="font-weight:500;">🤖 Gunakan AI Matching (Groq)</span>
        </label>

        <div class="small" style="margin-top:4px;color:#6b7280;">
          <span id="aiStatus">💡 AI Matching akan menggunakan Groq API untuk pencocokan lampiran yang lebih cerdas. Membutuhkan GROQ_API_KEY di environment.</span>
        </label>

        <div class="small" style="margin-top:8px;">
          Gunakan tombol <strong>📇 Buka Kontak (Slider)</strong> untuk memilih penerima:
          <ul style="margin:6px 0 0 18px;">
            <li><strong>Pilih Grup</strong> → penerima otomatis (tanpa ceklis manual)</li>
            <li><strong>Tidak pilih grup</strong> → bisa centang kontak manual</li>
          </ul>
          Terhitung: <strong><span id="selectedCount">0</span></strong> penerima.
        </div>
      </div>
      </div>
    </div>

    <button class="btn" type="submit">3) Preview &amp; Cocokkan (Similarity)</button>

    <!-- ===================================== -->
    <!-- DRAWER: Kontak & Pencarian            -->
    <!-- ===================================== -->
    <div id="drawerOverlay" class="drawer-overlay" aria-hidden="true">
      <aside id="contactDrawer" class="drawer" role="dialog" aria-modal="true" aria-labelledby="drawerTitle">
        <div class="drawer-header">
          <h4 id="drawerTitle" class="drawer-title">Kontak</h4>
          <div class="tools" style="align-items:flex-start;">
            <!-- Dropdown filter grup -->
            <div class="dropdown">
              <button type="button" id="groupDropdownBtn" class="btn secondary dropdown-btn" aria-haspopup="true" aria-expanded="false" aria-controls="groupDropdownPanel">
                Filter Grup ▾
              </button>
              <div id="groupDropdownPanel" class="dropdown-panel" role="menu" aria-label="Filter Grup &amp; Grup Order">
                <div class="panel-head">
                  <strong>Pilih Grup / Grup Order</strong>
                  <div class="small">Centang satu/lebih grup atau grup order — klik Terapkan</div>
                </div>
                
                <!-- Section Grup Order -->
                <div style="margin-bottom: 12px; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px;">
                  <div style="font-weight: 600; margin-bottom: 8px; color: #111;">Grup Order</div>
                  <div id="groupOrderList" class="panel-body" tabindex="-1">
                    <!-- Baris checkbox grup order dimuat via JS -->
                  </div>
                </div>
                
                <!-- Section Grup -->
                <div>
                  <div style="font-weight: 600; margin-bottom: 8px; color: #111;">Grup</div>
                  <div id="groupList" class="panel-body" tabindex="-1">
                    <!-- Baris checkbox grup dimuat via JS -->
                  </div>
                </div>
                
                <div class="panel-actions">
                  <button type="button" class="btn secondary" id="btnGroupsRefresh">Refresh</button>
                  <button type="button" class="btn warn" id="btnGroupsClear">Kosongkan</button>
                  <button type="button" class="btn success" id="btnGroupsSelectAll">Pilih semua</button>
                  <button type="button" class="btn" id="btnGroupsApply">Terapkan</button>
                </div>
              </div>
            </div>

            <span class="info-pill">Grup: <strong><span id="groupCount">0</span></strong></span>
            <span class="info-pill">Penerima: <strong><span id="selectedCount">0</span></strong></span>
            <span class="info-pill" id="ccPillInfo" style="display:none;">CC: <strong><span id="selectedCCCount">0</span></strong></span>
            <button type="button" class="btn secondary" onclick="closeDrawer()">Tutup</button>
          </div>

          <!-- Badge nama grup aktif -->
          <div id="groupBadges" class="badges" style="margin-top:6px;"></div>
        </div>

        <div class="drawer-body">
          <!-- Grup CC panel (CC mode only) -->
          <div id="ccGroupPanel" style="display:none;margin-bottom:12px;padding:10px 12px;background:#F1F8E9;border:1px solid #AED581;border-radius:8px;">
            <div style="font-weight:700;color:#33691E;margin-bottom:8px;font-size:0.9rem;">📋 Pilih dari Grup CC</div>
            <div id="ccGroupBtns" style="display:flex;flex-wrap:wrap;gap:6px;">
              <span style="color:#888;font-size:0.85rem;">⏳ Memuat grup CC...</span>
            </div>
          </div>

          <div class="tools" style="margin-bottom:8px;">
            <input id="contactSearch" class="input full" type="text" placeholder="Cari nama / email… (live search)">
          </div>

          <div class="info-bar">
            <span class="info-pill">Total: <strong><span id="totalCount"><?= (int)$totalContacts ?></span></strong></span>
            <span class="info-pill">Tampil: <strong><span id="visibleCount"><?= (int)$totalContacts ?></span></strong></span>
            <div id="recipientsModeButtons" style="display:flex;gap:8px;">
              <button type="button" class="btn success" onclick="selectAllFiltered(true)" title="Hanya berfungsi saat tidak memilih grup">Pilih semua (hasil filter)</button>
              <button type="button" class="btn warn" onclick="selectAllFiltered(false)" title="Hanya berfungsi saat tidak memilih grup">Uncheck semua (hasil filter)</button>
              <button type="button" class="btn secondary" onclick="clearAllSelection()" title="Hanya berfungsi saat tidak memilih grup">Bersihkan semua</button>
            </div>
          </div>

          <table id="contactTable" class="table table-sm" aria-label="Tabel kontak">
            <thead>
              <tr>
                <th style="width:42px;" id="thCheckbox">#</th>
                <th>Nama</th>
                <th>Email</th>
                <th style="width:110px;">Sumber</th>
              </tr>
            </thead>
            <tbody id="contactTbody">
              <!-- Rows dimuat via JS (AJAX) -->
            </tbody>
          </table>
        </div>

        <div class="drawer-footer">
          <span class="small" style="margin-right:auto;" id="drawerTip">Tip: Pilih Grup untuk auto recipients, atau biarkan kosong untuk pilih manual.</span>
          <button type="button" class="btn secondary" onclick="applyAndCloseDrawer()">Terapkan &amp; Tutup</button>
        </div>
      </aside>
    </div>
    <!-- /Drawer -->
  </form>
  <!-- /Main form (compose) -->
</main>

<!-- Quill Editor Initialization -->
<script>
// Configure Quill Editor with proper table support
const quill = new Quill('#editorContainer', {
  theme: 'snow',
  modules: {
    toolbar: [
      ['bold', 'italic', 'underline', 'strike'],
      ['blockquote', 'code-block'],
      [{ 'header': 1 }, { 'header': 2 }],
      [{ 'list': 'ordered'}, { 'list': 'bullet' }],
      [{ 'script': 'sub'}, { 'script': 'super' }],
      [{ 'indent': '-1'}, { 'indent': '+1' }],
      [{ 'size': ['small', false, 'large', 'huge'] }],
      [{ 'header': [false, 1, 2, 3, 4, 5, 6] }],
      [{ 'color': [] }, { 'background': [] }],
      [{ 'font': [] }],
      [{ 'align': [] }],
      ['link', 'image'],
      ['clean']
    ]
  }
});

// Custom image handler untuk upload langsung ke base64 (embedded di body)
const imageHandler = () => {
  const input = document.createElement('input');
  input.setAttribute('type', 'file');
  input.setAttribute('accept', 'image/*');
  input.click();

  input.onchange = () => {
    const file = input.files[0];
    if (!file) return;

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      alert('Ukuran gambar terlalu besar (max 5MB)');
      return;
    }

    // Convert to base64
    const reader = new FileReader();
    reader.onload = (e) => {
      const base64String = e.target.result;
      const range = quill.getSelection();
      if (range) {
        quill.insertEmbed(range.index, 'image', base64String);
      }
    };
    reader.readAsDataURL(file);
  };
};

// Register custom image handler
quill.getModule('toolbar').addHandler('image', imageHandler);

// Post-paste handler: Fix table formatting AFTER Quill processes the paste
quill.root.addEventListener('paste', function(e) {
  // Wait for Quill to process the paste
  setTimeout(() => {
    try {
      const tables = quill.root.querySelectorAll('table');
      tables.forEach(table => {
        // Ensure table styling
        if (!table.style.borderCollapse) table.style.borderCollapse = 'collapse';
        if (!table.style.width) table.style.width = '100%';
        
        // Process cells
        table.querySelectorAll('td, th').forEach(cell => {
          let needsUpdate = false;
          const style = cell.getAttribute('style') || '';
          
          // Check if borders exist
          if (!style.includes('border')) {
            cell.style.border = '1px solid #d1d5db';
            needsUpdate = true;
          }
          
          // Check if padding exists
          if (!style.includes('padding')) {
            cell.style.padding = '8px';
            needsUpdate = true;
          }
        });
      });
    } catch (err) {
      console.log('Table formatting error (non-fatal):', err);
    }
  }, 100);
}, false);

// Sync Quill content to hidden input before form submission
document.getElementById('composeForm')?.addEventListener('submit', function(e) {
  const bodyInput = document.getElementById('bodyInput');
  if (bodyInput) {
    bodyInput.value = quill.root.innerHTML;
  }
});

// Jika ada placeholder text, set ke Quill
quill.root.innerHTML = `<p>Yth. Bapak/Ibu,<br>Terlampir dokumen...<br>Terima kasih.</p>`;

// Page Transition Animations
function initPageTransitions() {
  const navLinks = document.querySelectorAll('.sidebar-nav a, .quick-actions a, main a[href$=".php"]');
  navLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      if (!href || href.startsWith('#') || href.startsWith('http')) return;
      e.preventDefault();
      const overlay = document.getElementById('pageTransition');
      const mainContent = document.querySelector('main') || document.querySelector('.main-content');
      
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
  const mainContent = document.querySelector('main') || document.querySelector('.main-content');
  if (overlay) {
    overlay.classList.remove('active');
    if (mainContent) mainContent.classList.remove('transitioning');
  }
});

// ===== COLLAPSIBLE SLIDER CARD =====
function toggleCardSlider(cardId) {
  var card = document.getElementById(cardId);
  if (!card) return;
  card.classList.toggle('collapsed');
  localStorage.setItem('compose_card_' + cardId, card.classList.contains('collapsed') ? '1' : '0');
}
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.card-collapsible').forEach(function(card) {
    var saved = localStorage.getItem('compose_card_' + card.id);
    if (saved === '0') card.classList.remove('collapsed');
    else if (saved === '1') card.classList.add('collapsed');
  });

  // Check if Groq API key is configured
  const aiCheckbox = document.getElementById('useAIMatching');
  const aiStatus = document.getElementById('aiStatus');
  if (aiCheckbox && aiStatus) {
    // We can't check server-side env vars from JS, so we'll show the option
    // The server will handle fallback if API key is not configured
    aiCheckbox.addEventListener('change', function() {
      if (this.checked) {
        aiStatus.textContent = '⚠️ AI Matching diaktifkan. Pastikan GROQ_API_KEY sudah diset di environment variable.';
        aiStatus.style.color = '#059669';
      } else {
        aiStatus.textContent = '💡 AI Matching akan menggunakan Groq API untuk pencocokan lampiran yang lebih cerdas. Membutuhkan GROQ_API_KEY di environment.';
        aiStatus.style.color = '#6b7280';
      }
    });
  }
});
</script>

<!-- AI Assistant Widget -->
<script src="../assets/js/ai-assistant.js?v=1.0"></script>

<!-- Page Transition Overlay -->
<div class="page-transition" id="pageTransition"></div>

</body>
</html>