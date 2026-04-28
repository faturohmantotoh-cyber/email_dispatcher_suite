<?php
// contacts.php — Drop-in replacement (Laragon / PHP / MySQL 8)
// Fixes:
//  - Quote identifier `groups` & `group_members` (MySQL 8 keyword GROUPS)
//  - Safe fetch group id after INSERT ... ON DUPLICATE KEY
//  - Validate & deduplicate contact IDs before insert to group_members
//  - Render "Daftar Grup" on dashboard (with member count)
//  - Fix broken header links (<a href="...">)

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';

ensure_dirs();
$pdo = DB::conn();

/**
 * Import contacts from CSV exported by PowerShell
 */
function import_contacts_csv(PDO $pdo, string $csvPath): array {
    $summary = ['processed' => 0, 'inserted_or_updated' => 0, 'skipped' => 0, 'errors' => 0, 'notes' => []];

    if (!file_exists($csvPath)) {
        $summary['notes'][] = "CSV not found: $csvPath";
        return $summary;
    }
    if (filesize($csvPath) === 0) {
        $summary['notes'][] = "CSV is empty (0 bytes)";
        return $summary;
    }

    $fp = fopen($csvPath, 'r');
    if (!$fp) {
        $summary['notes'][] = "Cannot open CSV: $csvPath";
        return $summary;
    }

    // Read header
    $header = fgetcsv($fp);
    if ($header === false || count($header) === 0) {
        fclose($fp);
        $summary['notes'][] = "Invalid CSV (header unreadable)";
        return $summary;
    }
    // Strip BOM for first cell
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }

    // Normalize header keys
    $map = [];
    foreach ($header as $i => $h) {
        $map[strtolower(trim((string)$h))] = $i;
    }
    $hasName  = array_key_exists('name', $map);
    $hasEmail = array_key_exists('email', $map);
    if (!$hasEmail) {
        fclose($fp);
        $summary['notes'][] = "CSV missing 'Email' header";
        return $summary;
    }

    // Prepare UPSERT
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO contacts(display_name, email, source, last_synced)
            VALUES(?, ?, 'Outlook', NOW())
            ON DUPLICATE KEY UPDATE
              display_name = VALUES(display_name),
              source = 'Outlook',
              last_synced = NOW()
        ");

        while (($row = fgetcsv($fp)) !== false) {
            $summary['processed']++;
            $email = trim((string)($row[$map['email']] ?? ''));
            if ($email === '') { $summary['skipped']++; continue; }

            $name = '';
            if ($hasName) { $name = trim((string)($row[$map['name']] ?? '')); }
            if ($name === '') { $name = $email; }

            try {
                $stmt->execute([$name, $email]);
                $summary['inserted_or_updated']++;
            } catch (Exception $ex) {
                $summary['errors']++;
                $summary['notes'][] = "ERR email {$email}: " . $ex->getMessage();
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $summary['notes'][] = "Transaction rollback: " . $e->getMessage();
    } finally {
        fclose($fp);
    }

    return $summary;
}

/**
 * Ensure group tables exist (quote identifiers to avoid MySQL 8 keyword conflict)
 */
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

/**
 * Ensure group order tables exist
 */
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

/**
 * Ensure CC group tables exist
 */
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

// Build message banner
$msg = '';

// === Action: SYNC (Export from Outlook → CSV → Auto Import to DB) ===
if (isset($_POST['sync'])) {
    $ps = __DIR__ . '/../ps/export_outlook_contacts.ps1';
    $csv = __DIR__ . '/../storage/contacts_export.csv';
    $account = get_sender_account();

    if (file_exists($csv)) @unlink($csv);

    // NOTE: On Windows, use double quotes for PowerShell -File arguments
    $cmd = 'powershell -ExecutionPolicy Bypass -File '
        . escapeshellarg($ps)
        . ' -Account ' . escapeshellarg($account)
        . ' -OutputCsv ' . escapeshellarg($csv);

    $output = shell_exec($cmd . ' 2>&1');

    // Parse output lines
    $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$output)));
    $msgContent = '<div style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;padding:12px;margin:8px 0;font-family:monospace;font-size:12px;">';
    $hasErrors = false;
    foreach ($lines as $line) {
        if (strpos($line, '[ERROR]') !== false) { $hasErrors = true; }
        if (strpos($line, '[ERROR]') !== false) {
            $msgContent .= '<div style="color:#dc3545;font-weight:bold;">' . e($line) . '</div>';
        } elseif (strpos($line, '[WARN]') !== false) {
            $msgContent .= '<div style="color:#fd7e14;">' . e($line) . '</div>';
        } elseif (strpos($line, '[OK]') !== false) {
            $msgContent .= '<div style="color:#28a745;">' . e($line) . '</div>';
        } else {
            $msgContent .= '<div>' . e($line) . '</div>';
        }
    }
    $msgContent .= '</div>';

    if (!$hasErrors && file_exists($csv) && filesize($csv) > 0) {
        $summary = import_contacts_csv($pdo, $csv);
        $bytes = filesize($csv);
        $msg  = "✓ <strong>Export &amp; Import selesai!</strong> CSV: {$bytes} bytes.<br>";
        $msg .= "Processed: <strong>{$summary['processed']}</strong>, ";
        $msg .= "Upserted: <strong>{$summary['inserted_or_updated']}</strong>, ";
        $msg .= "Skipped: <strong>{$summary['skipped']}</strong>, ";
        $msg .= "Errors: <strong>{$summary['errors']}</strong>";
        if (!empty($summary['notes'])) {
            $msg .= '<br><details style="margin-top:6px;"><summary>Detail</summary><ul style="margin:6px 0 0 16px;">';
            foreach ($summary['notes'] as $n) {
                $msg .= '<li>' . e($n) . '</li>';
            }
            $msg .= '</ul></details>';
        }
        $msg .= $msgContent;
    } else {
        $msg = '<span style="color:red">✗ <strong>Export gagal atau kosong!</strong></span><br>' . $msgContent;
    }
}

// === Action: MANUAL IMPORT (opsional) ===
if (isset($_POST['import'])) {
    $csv = __DIR__ . '/../storage/contacts_export.csv';
    if (!file_exists($csv)) {
        $msg = '<span style="color:red">✗ File CSV tidak ditemukan: ' . e($csv) . '</span><br><small>Silakan jalankan Export dari Outlook terlebih dahulu</small>';
    } elseif (filesize($csv) === 0) {
        $msg = '<span style="color:red">✗ File CSV kosong (0 bytes)</span><br><small>Mungkin tidak ada kontak di Outlook atau export gagal</small>';
    } else {
        $summary = import_contacts_csv($pdo, $csv);
        $msg  = "✓ Import selesai! ";
        $msg .= "Processed: <strong>{$summary['processed']}</strong>, ";
        $msg .= "Upserted: <strong>{$summary['inserted_or_updated']}</strong>, ";
        $msg .= "Skipped: <strong>{$summary['skipped']}</strong>, ";
        $msg .= "Errors: <strong>{$summary['errors']}</strong>";
        if (!empty($summary['notes'])) {
            $msg .= '<br><details style="margin-top:6px;"><summary>Detail</summary><ul style="margin:6px 0 0 16px;">';
            foreach ($summary['notes'] as $n) {
                $msg .= '<li>' . e($n) . '</li>';
            }
            $msg .= '</ul></details>';
        }
    }
}

// === Action: CLEAR ALL CONTACTS (sync history) ===
if (isset($_POST['clear_contacts'])) {
    try {
        $pdo->exec("DELETE FROM `contacts`");
        $msg = "✓ <strong>Semua kontak history berhasil dihapus!</strong> Database contacts sekarang kosong.";
    } catch (Exception $e) {
        $msg = '<span style="color:red">✗ Gagal menghapus kontak: ' . e($e->getMessage()) . '</span>';
    }
}

// === Action: CREATE GROUP from modal ===
if (isset($_POST['create_group']) && $_POST['create_group'] === '1') {
    $groupName = trim($_POST['group_name'] ?? '');
    $selected  = $_POST['contact_id'] ?? []; // array of contact IDs

    if ($groupName === '') {
        $msg = '<span style="color:red">✗ <strong>Nama grup wajib diisi.</strong></span>';
    } elseif (!is_array($selected) || count($selected) === 0) {
        $msg = '<span style="color:red">✗ <strong>Pilih minimal 1 kontak untuk dimasukkan ke grup.</strong></span>';
    } else {
        try {
            ensure_group_tables($pdo);
            $pdo->beginTransaction();

            // Normalize & deduplicate contact IDs
            $selectedIds = array_unique(array_map('intval', (array)$selected));
            $selectedIds = array_filter($selectedIds, fn($id) => $id > 0);

            if (empty($selectedIds)) {
                throw new RuntimeException('Tidak ada kontak yang valid');
            }

            // Verify IDs exist in contacts - use batch approach for large arrays
            $validIds = [];
            $batchSize = 600;
            $batches = array_chunk($selectedIds, $batchSize, false);
            
            foreach ($batches as $batch) {
                $placeholders = implode(',', array_fill(0, count($batch), '?'));
                $chk = $pdo->prepare("SELECT `id` FROM `contacts` WHERE `id` IN ($placeholders)");
                $chk->execute($batch);
                $validIds = array_merge($validIds, array_map('intval', $chk->fetchAll(PDO::FETCH_COLUMN)));
            }
            
            if (empty($validIds)) {
                throw new RuntimeException('Tidak ada kontak yang valid di database');
            }
            
            $validIds = array_unique($validIds);

            // Create or get group by name (quote identifiers!)
            $stmt = $pdo->prepare("
                INSERT INTO `groups`(`name`)
                VALUES(?)
                ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)
            ");
            $stmt->execute([$groupName]);

            $gid = $pdo->lastInsertId();
            if (!$gid) {
                // If duplicate: fetch id safely
                $sel = $pdo->prepare("SELECT `id` FROM `groups` WHERE `name` = ?");
                $sel->execute([$groupName]);
                $gid = (int)$sel->fetchColumn();
                if ($gid <= 0) {
                    throw new RuntimeException('Tidak bisa mengambil ID grup untuk nama: ' . $groupName);
                }
            } else {
                $gid = (int)$gid;
            }

            // Insert members one by one (avoid binding issues with large arrays)
            $ins = $pdo->prepare("INSERT IGNORE INTO `group_members`(`group_id`, `contact_id`) VALUES(?, ?)");
            $count = 0;
            foreach ($validIds as $cid) {
                try {
                    $ins->execute([$gid, $cid]);
                    if ($ins->rowCount() > 0) {
                        $count++;
                    }
                } catch (Exception $e) {
                    error_log("Failed to add contact $cid to group $gid: " . $e->getMessage());
                    // Continue with next contact
                }
            }

            $pdo->commit();
            $msg = '<div style="background:#E3F2FD;border-left:4px solid #0052CC;color:#0052CC;padding:12px;border-radius:4px;margin:8px 0;">✓ Grup <strong>' . e($groupName) . '</strong> berhasil dibuat/diperbarui. Anggota ditambahkan: <strong>' . $count . ' dari ' . count($validIds) . '</strong>.</div>';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ Gagal membuat grup: ' . e($e->getMessage()) . '</div>';
        }
    }
}

// === Action: UPDATE GROUP members ===
if (isset($_POST['update_group']) && $_POST['update_group'] === '1') {
    $groupId = (int)($_POST['group_id'] ?? 0);
    $groupName = trim($_POST['group_name'] ?? '');
    $selected = $_POST['contact_id'] ?? [];

    if ($groupId <= 0) {
        $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ ID grup tidak valid.</div>';
    } elseif ($groupName === '') {
        $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ Nama grup tidak boleh kosong.</div>';
    } elseif (!is_array($selected) || count($selected) === 0) {
        $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ Pilih minimal 1 kontak untuk grup.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update group name
            $updateNameStmt = $pdo->prepare("UPDATE `groups` SET `name` = ? WHERE `id` = ?");
            $updateNameStmt->execute([$groupName, $groupId]);

            // Normalize & deduplicate contact IDs
            $selectedIds = array_unique(array_map('intval', (array)$selected));
            $selectedIds = array_filter($selectedIds, fn($id) => $id > 0);

            if (empty($selectedIds)) {
                throw new RuntimeException('Tidak ada kontak yang valid');
            }

            // Verify IDs exist in contacts - use IN clause with reasonable batch size
            $validIds = [];
            $batchSize = 600; // MySQL default max_allowed_packet can handle ~600 placeholders
            $batches = array_chunk($selectedIds, $batchSize, false);
            
            foreach ($batches as $batch) {
                $placeholders = implode(',', array_fill(0, count($batch), '?'));
                $chk = $pdo->prepare("SELECT `id` FROM `contacts` WHERE `id` IN ($placeholders)");
                $chk->execute($batch);
                $validIds = array_merge($validIds, array_map('intval', $chk->fetchAll(PDO::FETCH_COLUMN)));
            }
            
            if (empty($validIds)) {
                throw new RuntimeException('Tidak ada kontak yang valid di database');
            }
            
            $validIds = array_unique($validIds);

            // Step 1: Get current members in group
            $currentStmt = $pdo->prepare("SELECT `contact_id` FROM `group_members` WHERE `group_id` = ?");
            $currentStmt->execute([$groupId]);
            $currentMembers = array_map('intval', $currentStmt->fetchAll(PDO::FETCH_COLUMN));

            // Step 2: Find members to add (new members not in current list)
            $toAdd = array_diff($validIds, $currentMembers);
            
            // Step 3: Find members to remove (current members not in selected list)
            $toRemove = array_diff($currentMembers, $validIds);

            // Step 4: Insert new members (one by one)
            if (!empty($toAdd)) {
                $insStmt = $pdo->prepare("INSERT IGNORE INTO `group_members`(`group_id`, `contact_id`) VALUES(?, ?)");
                foreach ($toAdd as $cid) {
                    $insStmt->execute([$groupId, $cid]);
                }
            }

            // Step 5: Delete removed members (using batch delete)
            if (!empty($toRemove)) {
                $delBatches = array_chunk($toRemove, $batchSize, false);
                foreach ($delBatches as $batch) {
                    $placeholders = implode(',', array_fill(0, count($batch), '?'));
                    $delStmt = $pdo->prepare("DELETE FROM `group_members` WHERE `group_id` = ? AND `contact_id` IN ($placeholders)");
                    $params = array_merge([$groupId], $batch);
                    $delStmt->execute($params);
                }
            }

            $pdo->commit();
            $msg = '<div style="background:#E3F2FD;border-left:4px solid #0052CC;color:#0052CC;padding:12px;border-radius:4px;margin:8px 0;">✓ Grup <strong>' . e($groupName) . '</strong> berhasil diperbarui! Total anggota: ' . count($validIds) . ' (Ditambah: ' . count($toAdd) . ', Dihapus: ' . count($toRemove) . ')</div>';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ Gagal memperbarui grup: ' . e($e->getMessage()) . '</div>';
        }
    }
}

// === Action: GET GROUP MEMBERS (AJAX) ===
if (isset($_GET['action']) && $_GET['action'] === 'get_group_members') {
    header('Content-Type: application/json');
    $groupId = (int)($_GET['group_id'] ?? 0);
    
    if ($groupId <= 0) {
        echo json_encode(['error' => 'Invalid group ID']);
        exit;
    }
    
    try {
        ensure_group_tables($pdo);
        
        // Get group name
        $stmt = $pdo->prepare("SELECT `name` FROM `groups` WHERE `id` = ?");
        $stmt->execute([$groupId]);
        $groupName = $stmt->fetchColumn();
        
        if (!$groupName) {
            echo json_encode(['error' => 'Group not found']);
            exit;
        }
        
        // Get member IDs
        $stmt = $pdo->prepare("SELECT `contact_id` FROM `group_members` WHERE `group_id` = ?");
        $stmt->execute([$groupId]);
        $members = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        
        // Get member details
        $memberDetails = [];
        if (!empty($members)) {
            $placeholders = implode(',', array_fill(0, count($members), '?'));
            $stmt = $pdo->prepare("SELECT `id`, `contact_id` FROM `group_members` WHERE `group_id` = ? ORDER BY `contact_id`");
            $stmt->execute([$groupId]);
            $memberDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'order_name' => $groupName,
            'members' => $memberDetails
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// === Action: DELETE GROUP ===
if (isset($_POST['delete_group'])) {
    $groupId = (int)($_POST['delete_group'] ?? 0);
    
    if ($groupId <= 0) {
        $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ ID grup tidak valid.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Delete members
            $del = $pdo->prepare("DELETE FROM `group_members` WHERE `group_id` = ?");
            $del->execute([$groupId]);
            
            // Delete group
            $del = $pdo->prepare("DELETE FROM `groups` WHERE `id` = ?");
            $del->execute([$groupId]);
            
            $pdo->commit();
            $msg = '<div style="background:#E3F2FD;border-left:4px solid #0052CC;color:#0052CC;padding:12px;border-radius:4px;margin:8px 0;">✓ Grup berhasil dihapus!</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ Gagal menghapus grup: ' . e($e->getMessage()) . '</div>';
        }
    }
}

// === Action: CREATE GROUP ORDER ===
if (isset($_POST['create_group_order'])) {
    $orderName = trim($_POST['group_order_name'] ?? '');
    $selectedGroups = $_POST['group_id'] ?? [];

    if ($orderName === '') {
        $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ <strong>Nama grup order wajib diisi.</strong></div>';
    } elseif (!is_array($selectedGroups) || count($selectedGroups) === 0) {
        $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ <strong>Pilih minimal 1 grup untuk dimasukkan ke grup order.</strong></div>';
    } else {
        try {
            ensure_group_order_tables($pdo);
            $pdo->beginTransaction();

            // Create or get group order by name
            $stmt = $pdo->prepare("
                INSERT INTO `group_orders`(`name`)
                VALUES(?)
                ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)
            ");
            $stmt->execute([$orderName]);

            $goid = $pdo->lastInsertId();
            if (!$goid) {
                // If duplicate: fetch id safely
                $sel = $pdo->prepare("SELECT `id` FROM `group_orders` WHERE `name` = ?");
                $sel->execute([$orderName]);
                $goid = (int)$sel->fetchColumn();
                if ($goid <= 0) {
                    throw new RuntimeException('Tidak bisa mengambil ID grup order untuk nama: ' . $orderName);
                }
            } else {
                $goid = (int)$goid;
            }

            // Normalize & deduplicate group IDs
            $selectedIds = array_unique(array_map('intval', (array)$selectedGroups));

            // Ensure IDs exist in groups (avoid FK error)
            if (!empty($selectedIds)) {
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $chk = $pdo->prepare("SELECT `id` FROM `groups` WHERE `id` IN ($placeholders)");
                $chk->execute($selectedIds);
                $existing = array_map('intval', $chk->fetchAll(PDO::FETCH_COLUMN));
                $selectedIds = $existing;
            }

            // Delete existing items then insert new ones
            $del = $pdo->prepare("DELETE FROM `group_order_items` WHERE `group_order_id` = ?");
            $del->execute([$goid]);

            $ins = $pdo->prepare("INSERT INTO `group_order_items`(`group_order_id`, `group_id`) VALUES(?, ?)");
            $count = 0;
            foreach ($selectedIds as $gid) {
                if ($gid > 0) {
                    $ins->execute([$goid, $gid]);
                    if ($ins->rowCount() > 0) {
                        $count++;
                    }
                }
            }

            $pdo->commit();
            $msg = '<div style="background:#E3F2FD;border-left:4px solid #0052CC;color:#0052CC;padding:12px;border-radius:4px;margin:8px 0;">✓ Grup Order <strong>' . e($orderName) . '</strong> berhasil dibuat/diperbarui. Grup ditambahkan: <strong>' . $count . '</strong>.</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ Gagal membuat grup order: ' . e($e->getMessage()) . '</div>';
        }
    }
}

// === Action: GET GROUP ORDER ITEMS (AJAX) ===
if (isset($_GET['action']) && $_GET['action'] === 'get_group_order_items') {
    header('Content-Type: application/json');
    $groupOrderId = (int)($_GET['group_order_id'] ?? 0);
    
    if ($groupOrderId <= 0) {
        echo json_encode(['error' => 'Invalid group order ID']);
        exit;
    }
    
    try {
        ensure_group_order_tables($pdo);
        
        // Get group order name
        $stmt = $pdo->prepare("SELECT `name` FROM `group_orders` WHERE `id` = ?");
        $stmt->execute([$groupOrderId]);
        $orderName = $stmt->fetchColumn();
        
        if (!$orderName) {
            echo json_encode(['error' => 'Group order not found']);
            exit;
        }
        
        // Get group IDs in this order
        $stmt = $pdo->prepare("SELECT `group_id` FROM `group_order_items` WHERE `group_order_id` = ?");
        $stmt->execute([$groupOrderId]);
        $members = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        
        echo json_encode([
            'order_name' => $orderName,
            'groups' => $members
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// === Action: GET CC GROUP MEMBERS (AJAX) ===
if (isset($_GET['action']) && $_GET['action'] === 'get_cc_group_members') {
    header('Content-Type: application/json');
    $ccGroupId = (int)($_GET['cc_group_id'] ?? 0);
    if ($ccGroupId <= 0) {
        echo json_encode(['error' => 'Invalid CC group ID']);
        exit;
    }
    try {
        ensure_cc_group_tables($pdo);
        $stmt = $pdo->prepare("SELECT `name` FROM `cc_groups` WHERE `id` = ?");
        $stmt->execute([$ccGroupId]);
        $groupName = $stmt->fetchColumn();
        if (!$groupName) {
            echo json_encode(['error' => 'CC group not found']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT `contact_id` FROM `cc_group_members` WHERE `cc_group_id` = ?");
        $stmt->execute([$ccGroupId]);
        $memberIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        echo json_encode(['cc_group_name' => $groupName, 'member_ids' => $memberIds]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// === Action: CREATE / UPDATE CC GROUP ===
if (isset($_POST['create_cc_group'])) {
    $ccGroupId  = (int)($_POST['cc_group_id'] ?? 0);
    $ccGroupName = trim($_POST['cc_group_name'] ?? '');
    $selected    = $_POST['cc_contact_id'] ?? [];
    if ($ccGroupName === '') {
        $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ <strong>Nama grup CC wajib diisi.</strong></div>';
    } elseif (!is_array($selected) || count($selected) === 0) {
        $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ <strong>Pilih minimal 1 kontak untuk grup CC.</strong></div>';
    } else {
        try {
            ensure_cc_group_tables($pdo);
            $pdo->beginTransaction();
            if ($ccGroupId > 0) {
                $pdo->prepare("UPDATE `cc_groups` SET `name` = ? WHERE `id` = ?")->execute([$ccGroupName, $ccGroupId]);
                $pdo->prepare("DELETE FROM `cc_group_members` WHERE `cc_group_id` = ?")->execute([$ccGroupId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO `cc_groups`(`name`) VALUES(?) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");
                $stmt->execute([$ccGroupName]);
                $ccGroupId = (int)$pdo->lastInsertId();
                if ($ccGroupId <= 0) {
                    $sel = $pdo->prepare("SELECT `id` FROM `cc_groups` WHERE `name` = ?");
                    $sel->execute([$ccGroupName]);
                    $ccGroupId = (int)$sel->fetchColumn();
                }
            }
            $selectedIds = array_unique(array_map('intval', (array)$selected));
            $selectedIds = array_filter($selectedIds, fn($id) => $id > 0);
            if (!empty($selectedIds)) {
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $chk = $pdo->prepare("SELECT `id` FROM `contacts` WHERE `id` IN ($placeholders)");
                $chk->execute(array_values($selectedIds));
                $validIds = array_map('intval', $chk->fetchAll(PDO::FETCH_COLUMN));
            } else {
                $validIds = [];
            }
            $ins = $pdo->prepare("INSERT IGNORE INTO `cc_group_members`(`cc_group_id`, `contact_id`) VALUES(?, ?)");
            $count = 0;
            foreach ($validIds as $cid) {
                $ins->execute([$ccGroupId, $cid]);
                $count += $ins->rowCount();
            }
            $pdo->commit();
            $msg = '<div style="background:#E3F2FD;border-left:4px solid #0052CC;color:#0052CC;padding:12px;border-radius:4px;margin:8px 0;">✓ Grup CC <strong>' . e($ccGroupName) . '</strong> berhasil disimpan. Anggota: <strong>' . $count . '</strong>.</div>';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ Gagal menyimpan grup CC: ' . e($e->getMessage()) . '</div>';
        }
    }
}

// === Action: DELETE CC GROUP ===
if (isset($_POST['delete_cc_group'])) {
    $ccGroupId = (int)($_POST['delete_cc_group'] ?? 0);
    if ($ccGroupId <= 0) {
        $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ ID grup CC tidak valid.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM `cc_group_members` WHERE `cc_group_id` = ?")->execute([$ccGroupId]);
            $pdo->prepare("DELETE FROM `cc_groups` WHERE `id` = ?")->execute([$ccGroupId]);
            $pdo->commit();
            $msg = '<div style="background:#E3F2FD;border-left:4px solid #0052CC;color:#0052CC;padding:12px;border-radius:4px;margin:8px 0;">✓ Grup CC berhasil dihapus!</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ Gagal menghapus grup CC: ' . e($e->getMessage()) . '</div>';
        }
    }
}

// === Action: DELETE GROUP ORDER ===
if (isset($_POST['delete_group_order'])) {
    $groupOrderId = (int)($_POST['delete_group_order'] ?? 0);
    
    if ($groupOrderId <= 0) {
        $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ ID grup order tidak valid.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Delete items
            $del = $pdo->prepare("DELETE FROM `group_order_items` WHERE `group_order_id` = ?");
            $del->execute([$groupOrderId]);
            
            // Delete group order
            $del = $pdo->prepare("DELETE FROM `group_orders` WHERE `id` = ?");
            $del->execute([$groupOrderId]);
            
            $pdo->commit();
            $msg = '<div style="background:#E3F2FD;border-left:4px solid #0052CC;color:#0052CC;padding:12px;border-radius:4px;margin:8px 0;">✓ Grup Order berhasil dihapus!</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = '<div style="background:#FADDD1;border-left:4px solid #E74C3C;color:#7F4028;padding:12px;border-radius:4px;margin:8px 0;">✗ Gagal menghapus grup order: ' . e($e->getMessage()) . '</div>';
        }
    }
}

// --- Fetch data for dashboard ---

// Contacts
$rows = $pdo->query("
    SELECT id, display_name, email, source, last_synced
    FROM contacts
    ORDER BY display_name
")->fetchAll(PDO::FETCH_ASSOC);

// Groups (with member counts)
try {
    ensure_group_tables($pdo); // ensure exists before select (idempotent)
    $groups = $pdo->query("
        SELECT g.`id`, g.`name`, COUNT(gm.`contact_id`) AS members
        FROM `groups` g
        LEFT JOIN `group_members` gm ON gm.`group_id` = g.`id`
        GROUP BY g.`id`, g.`name`
        ORDER BY g.`name`
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Jika table belum ada / error lain, jangan blokir halaman
    $groups = [];
}

// Group Orders (with group counts)
try {
    ensure_group_order_tables($pdo);
    $groupOrders = $pdo->query("
        SELECT go.`id`, go.`name`, COUNT(goi.`group_id`) AS group_count
        FROM `group_orders` go
        LEFT JOIN `group_order_items` goi ON goi.`group_order_id` = go.`id`
        GROUP BY go.`id`, go.`name`
        ORDER BY go.`name`
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $groupOrders = [];
}

// CC Groups (with member counts)
try {
    ensure_cc_group_tables($pdo);
    $ccGroups = $pdo->query("
        SELECT cg.`id`, cg.`name`, COUNT(cgm.`contact_id`) AS members
        FROM `cc_groups` cg
        LEFT JOIN `cc_group_members` cgm ON cgm.`cc_group_id` = cg.`id`
        GROUP BY cg.`id`, cg.`name`
        ORDER BY cg.`name`
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ccGroups = [];
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Kontak</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
* { box-sizing: border-box; }
body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; background: #FFFFFF; color: #1B1B1B; font-size: 15px; letter-spacing: -0.01em; }
header { background: #0052CC; color: white; padding: 1.5rem 2rem; margin: 0; box-shadow: 0 4px 12px rgba(0, 82, 204, 0.15); }
header h2 { margin: 0 0 0.75rem 0; font-size: 1.75rem; font-weight: 700; }
main { padding: 2rem; max-width: 1200px; margin: 0 auto; }
.tools { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; }
.table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
.table th { background: #E3F2FD; padding: 1.25rem 1rem; text-align: left; font-weight: 700; color: #0052CC; font-size: 0.95rem; border-bottom: 2px solid #0052CC; text-transform: uppercase; letter-spacing: 0.5px; }
.table td { padding: 1rem; border-bottom: 1px solid #E8E8E8; color: #424242; font-size: 0.95rem; font-weight: 500; }
.table tbody tr:hover { background: #F5F5F5; }
.btn { display: inline-flex; align-items: center; gap: 0.5rem; background: #0052CC; color: white; padding: 0.65rem 1rem; border-radius: 0.6rem; text-decoration: none; border: none; cursor: pointer; font-weight: 700; font-size: 0.9rem; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(0, 82, 204, 0.3); white-space: nowrap; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0, 82, 204, 0.4); }
.btn.secondary { background: #F0F0F0; color: #0052CC; }
.btn.secondary:hover { background: #E8E8E8; }
.btn.success { background: #0052CC; box-shadow: 0 2px 8px rgba(0, 82, 204, 0.3); }
.btn.warn { background: linear-gradient(135deg, #FFB84D 0%, #FF9966 100%); box-shadow: 0 2px 8px rgba(255, 184, 77, 0.3); }
.btn.danger { background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%); box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3); }
.tools { display: flex; gap: 0.5rem; flex-wrap: nowrap; margin-top: 1rem; overflow-x: auto; align-items: center; }
.card { background: white; border: 1.5px solid #E3F2FD; border-radius: 0.875rem; margin-bottom: 1.5rem; box-shadow: 0 2px 4px rgba(0, 82, 204, 0.08); overflow: hidden; }
.card:hover { box-shadow: 0 4px 12px rgba(0, 82, 204, 0.12); border-color: #0052CC; }
.card h3 { margin: 0; padding: 1.25rem 1.5rem; border-bottom: 2px solid #E3F2FD; background: #F5F8FF; font-size: 1.35rem; color: #0052CC; font-weight: 700; }
.card .body { padding: 1.75rem; }
.input, input[type="text"], input[type="hidden"], select { padding: 0.85rem 1rem; border: 1.5px solid #E3F2FD; border-radius: 0.6rem; font-size: 0.95rem; font-family: inherit; background: white; color: #1B1B1B; transition: all 0.2s ease; width: 100%; font-weight: 500; }
input:focus, select:focus { outline: none; border-color: #0052CC; box-shadow: 0 0 0 4px rgba(0, 82, 204, 0.15); }
input[type="checkbox"] { width: 1.2rem; height: 1.2rem; cursor: pointer; accent-color: #0052CC; margin-right: 0.5rem; }
.small { font-size: 0.85rem; color: #616161; margin: 0.5rem 0; font-weight: 500; }
.badge { display: inline-block; padding: 0.5rem 0.875rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; background: #E3F2FD; color: #0052CC; border: 1.5px solid #0052CC; margin: 0.25rem; }
.muted { color: #757575; font-weight: 500; }

/* Modal Styles */
.modal-overlay { position: fixed; inset: 0; background: rgba(45, 55, 72, 0.6); opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; z-index: 999; backdrop-filter: blur(2px); }
.modal-overlay.open { opacity: 1; visibility: visible; }
.modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.9); background: white; border-radius: 0.875rem; box-shadow: 0 20px 40px rgba(45, 55, 72, 0.25); z-index: 1000; max-width: 600px; width: 90vw; max-height: 90vh; overflow-y: auto; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
.modal-overlay.open .modal { opacity: 1; visibility: visible; transform: translate(-50%, -50%) scale(1); }
.modal-header { padding: 1.75rem; border-bottom: 2px solid #E3F2FD; background: #F5F8FF; flex-shrink: 0; }
.modal-title { font-weight: 700; margin: 0; color: #0052CC; font-size: 1.35rem; }
.modal-body { padding: 1.75rem; }
.modal-footer { padding: 1.5rem; border-top: 1.5px solid #E3F2FD; background: #F9FAFB; display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap; }
.modal .close-btn { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #0052CC; }

/* Drawer Styles */
.drawer-overlay { position: fixed; inset: 0; background: rgba(45, 55, 72, 0.6); opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; z-index: 999; backdrop-filter: blur(2px); }
.drawer-overlay.open { opacity: 1; visibility: visible; }
.drawer { position: fixed; top: 0; right: 0; height: 100vh; width: 500px; max-width: 95vw; background: white; box-shadow: 0 20px 40px rgba(45, 55, 72, 0.25); transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1000; display: flex; flex-direction: column; }
.drawer-overlay.open .drawer { transform: translateX(0); }
.drawer-header { padding: 1.75rem; border-bottom: 2px solid #E3F2FD; background: #F5F8FF; flex-shrink: 0; }
.drawer-title { font-weight: 700; margin: 0; color: #0052CC; font-size: 1.35rem; }
.drawer-body { padding: 1.5rem; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.drawer-footer { padding: 1.5rem; border-top: 1.5px solid #E3F2FD; background: #F9FAFB; display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap; }
.contact-item { padding: 0.75rem; margin-bottom: 0.5rem; border: 1px solid #E8E8E8; border-radius: 0.6rem; display: flex; align-items: center; gap: 0.75rem; cursor: pointer; transition: all 0.2s ease; }
.contact-item:hover { background: #F5F8FF; border-color: #0052CC; }
.contact-item input { margin: 0; }
.contact-name { font-weight: 600; color: #0052CC; }
.contact-email { font-size: 0.85rem; color: #616161; }
.contact-counter { display: inline-block; background: #0052CC; color: white; border-radius: 9999px; padding: 0.3rem 0.75rem; font-size: 0.8rem; font-weight: 700; }

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

/* Dynamic Card Styles */
.card-toolbar { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; padding: 1rem 1.5rem; background: #F9FAFB; border-bottom: 1px solid #E3F2FD; }
.card-toolbar .search-input { flex: 1; min-width: 180px; padding: 0.6rem 1rem; border: 1.5px solid #E3F2FD; border-radius: 0.6rem; font-size: 0.9rem; background: white; transition: border-color 0.2s; }
.card-toolbar .search-input:focus { outline: none; border-color: #0052CC; box-shadow: 0 0 0 3px rgba(0,82,204,0.12); }
.card-toolbar .info-badge { font-size: 0.82rem; font-weight: 600; color: #616161; white-space: nowrap; }
.card-toolbar .info-badge strong { color: #0052CC; }
.table-scroll { max-height: 420px; overflow-y: auto; }
.card-pagination { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; padding: 0.85rem 1.5rem; background: #F9FAFB; border-top: 1px solid #E3F2FD; font-size: 0.85rem; }
.card-pagination select { padding: 0.35rem 0.5rem; border: 1.5px solid #E3F2FD; border-radius: 0.4rem; font-size: 0.85rem; background: white; cursor: pointer; }
.card-pagination .page-btns { display: flex; gap: 0.35rem; }
.card-pagination .page-btn { padding: 0.35rem 0.7rem; border: 1.5px solid #E3F2FD; border-radius: 0.4rem; background: white; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: #424242; transition: all 0.15s; }
.card-pagination .page-btn:hover { background: #E3F2FD; border-color: #0052CC; color: #0052CC; }
.card-pagination .page-btn.active { background: #0052CC; color: white; border-color: #0052CC; }
.card-pagination .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.card-pagination .page-info { color: #616161; font-weight: 500; }

/* Collapsible Slider Card */
.card-collapsible h3 { cursor: pointer; user-select: none; display: flex; align-items: center; justify-content: space-between; }
.card-collapsible h3 .toggle-icon { font-size: 1.1rem; transition: transform 0.3s ease; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: rgba(0,82,204,0.1); }
.card-collapsible.collapsed h3 .toggle-icon { transform: rotate(-90deg); }
.card-collapsible .card-slider { max-height: 2000px; overflow: hidden; transition: max-height 0.45s cubic-bezier(0.4,0,0.2,1), opacity 0.3s ease; opacity: 1; }
.card-collapsible.collapsed .card-slider { max-height: 0; opacity: 0; }
.card-collapsible h3 .slider-hint { font-size: 0.78rem; font-weight: 500; color: #90A4AE; margin-left: auto; margin-right: 0.5rem; opacity: 0; transition: opacity 0.3s; }
.card-collapsible.collapsed h3 .slider-hint { opacity: 1; }
</style>
</head>
<body>
<header>
  <h2 style="margin:0;color:white;">📇 Kelola Kontak & Grup</h2>
  <div class="tools">
    <a class="btn" href="index.php">⟵ Dashboard</a>
    <button class="btn" type="button" onclick="openGroupModal()">➕ Buat Grup</button>
    <button class="btn" type="button" onclick="openGroupOrderModal()">📦 Grup Order</button>
    <button class="btn" type="button" onclick="openCCGroupModal()">📋 Grup CC</button>
    <button class="btn" type="button" onclick="openManualUpdateModal()">🔄 Update Grup</button>
  </div>
</header>
<main>
  <div class="card card-collapsible collapsed" id="cardSync">
    <h3 onclick="toggleCardSlider('cardSync')">
      <span>📧 Sinkronisasi Outlook</span>
      <span class="slider-hint">Klik untuk membuka</span>
      <span class="toggle-icon">▼</span>
    </h3>
    <div class="card-slider">
      <div class="body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;margin-bottom:16px;">
          <form method="post" style="display:flex;flex-direction:column;">
            <button class="btn success" name="sync" value="1" onclick="return confirm('Jalankan export kontak dari Outlook dan update database sekarang?')" style="width:100%;">📧 Export Outlook</button>
            <small style="color:#999;margin-top:4px;text-align:center;">Update kontak dari Outlook</small>
          </form>
          <form method="post" style="display:flex;flex-direction:column;">
            <button class="btn secondary" name="import" value="1" style="width:100%;">📁 Import CSV Ke DB</button>
            <small style="color:#999;margin-top:4px;text-align:center;">Import dari /storage/</small>
          </form>
          <div style="display:flex;flex-direction:column;">
            <button class="btn" type="button" onclick="openUploadManualModal()" style="width:100%;">📤 Upload Manual</button>
            <small style="color:#999;margin-top:4px;text-align:center;">Upload list kontak</small>
          </div>
        </div>
        <p><?= $msg ?></p>
        <p class="small">
          File CSV: <code>/storage/contacts_export.csv</code> • Akun: <span class="badge"><?= e(get_sender_account()) ?></span>
        </p>
      </div>
    </div>
  </div>

  <div class="card" id="group-builder" style="display:none;">
    <h3>Buat Group List dari Kontak</h3>
    <div class="body">
      <form method="post" id="groupForm">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
          <input class="input" type="text" name="group_name" placeholder="Nama grup, misal: Vendor ASAHIMAS" style="flex:1" required />
          <button class="btn" type="button" id="btnSelectAll">Pilih semua</button>
          <button class="btn warn" type="button" id="btnClear">Bersihkan</button>
          <button class="btn success" type="submit" name="create_group" value="1">Simpan Grup</button>
        </div>

        <div class="small" style="margin-bottom:8px;">Centang kontak yang akan dimasukkan ke grup. Gunakan kolom pencarian di browser (Ctrl+F) bila diperlukan.</div>

        <table class="table">
          <thead>
            <tr>
              <th style="width:36px;">#</th>
              <th>Nama</th>
              <th>Email</th>
              <th>Sumber</th>
              <th>Last Sync</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><input type="checkbox" name="contact_id[]" value="<?= (int)$r['id'] ?>" /></td>
              <td><?= e((string)$r['display_name']) ?></td>
              <td><?= e((string)$r['email']) ?></td>
              <td><?= e((string)$r['source']) ?></td>
              <td><?= e((string)$r['last_synced']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </form>
    </div>
  </div>

  <div class="card card-collapsible" id="cardGrup">
    <h3 onclick="toggleCardSlider('cardGrup')">
      <span>📂 Daftar Grup</span>
      <span class="slider-hint">Klik untuk membuka</span>
      <span class="toggle-icon">▼</span>
    </h3>
    <div class="card-slider">
    <?php if (empty($groups)): ?>
      <div class="body"><div class="muted">Belum ada grup. Klik "Buat Grup Baru" untuk membuat grup baru.</div></div>
    <?php else: ?>
      <div class="card-toolbar">
        <input type="text" class="search-input" id="searchGrup" placeholder="🔍 Cari grup..." oninput="filterTable('Grup')">
        <div class="info-badge">Total: <strong id="countGrup"><?= count($groups) ?></strong> grup</div>
      </div>
      <div class="table-scroll">
        <table class="table" id="tableGrup">
          <thead>
            <tr>
              <th>Nama Grup</th>
              <th>Jumlah Anggota</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($groups as $g): ?>
            <tr>
              <td><?= e((string)$g['name']) ?></td>
              <td><span class="badge"><?= (int)$g['members'] ?> anggota</span></td>
              <td>
                <button class="btn secondary" type="button" style="padding:0.5rem 0.875rem;font-size:0.85rem;" onclick="openGroupModal(<?= (int)$g['id'] ?>)">✏️ Edit</button>
                <button class="btn danger" type="button" style="padding:0.5rem 0.875rem;font-size:0.85rem;" onclick="deleteGroup(<?= (int)$g['id'] ?>, '<?= addslashes(e((string)$g['name'])) ?>')">🗑️ Hapus</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-pagination" id="paginationGrup">
        <div style="display:flex;align-items:center;gap:0.5rem;"><span>Tampilkan</span><select id="perPageGrup" onchange="filterTable('Grup')"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="-1">Semua</option></select><span>baris</span></div>
        <div class="page-info" id="pageInfoGrup"></div>
        <div class="page-btns" id="pageBtnsGrup"></div>
      </div>
    <?php endif; ?>
    </div>
  </div>

  <div class="card card-collapsible" id="cardGrupOrder">
    <h3 onclick="toggleCardSlider('cardGrupOrder')">
      <span>📦 Daftar Grup Order</span>
      <span class="slider-hint">Klik untuk membuka</span>
      <span class="toggle-icon">▼</span>
    </h3>
    <div class="card-slider">
    <?php if (empty($groupOrders)): ?>
      <div class="body"><div class="muted">Belum ada grup order. Klik "Buat Grup Order" untuk membuat grup order baru.</div></div>
    <?php else: ?>
      <div class="card-toolbar">
        <input type="text" class="search-input" id="searchGrupOrder" placeholder="🔍 Cari grup order..." oninput="filterTable('GrupOrder')">
        <div class="info-badge">Total: <strong id="countGrupOrder"><?= count($groupOrders) ?></strong> grup order</div>
      </div>
      <div class="table-scroll">
        <table class="table" id="tableGrupOrder">
          <thead>
            <tr>
              <th>Nama Grup Order</th>
              <th>Jumlah Grup</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($groupOrders as $go): ?>
            <tr>
              <td><?= e((string)$go['name']) ?></td>
              <td><span class="badge"><?= (int)$go['group_count'] ?> grup</span></td>
              <td>
                <button class="btn secondary" type="button" style="padding:0.5rem 0.875rem;font-size:0.85rem;" onclick="openGroupOrderModal(<?= (int)$go['id'] ?>)">✏️ Edit</button>
                <button class="btn danger" type="button" style="padding:0.5rem 0.875rem;font-size:0.85rem;" onclick="deleteGroupOrder(<?= (int)$go['id'] ?>, '<?= addslashes(e((string)$go['name'])) ?>')">🗑️ Hapus</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-pagination" id="paginationGrupOrder">
        <div style="display:flex;align-items:center;gap:0.5rem;"><span>Tampilkan</span><select id="perPageGrupOrder" onchange="filterTable('GrupOrder')"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="-1">Semua</option></select><span>baris</span></div>
        <div class="page-info" id="pageInfoGrupOrder"></div>
        <div class="page-btns" id="pageBtnsGrupOrder"></div>
      </div>
    <?php endif; ?>
    </div>
  </div>

  <div class="card card-collapsible" id="cardGrupCC">
    <h3 onclick="toggleCardSlider('cardGrupCC')">
      <span>📋 Daftar Grup CC</span>
      <span class="slider-hint">Klik untuk membuka</span>
      <span class="toggle-icon">▼</span>
    </h3>
    <div class="card-slider">
    <?php if (empty($ccGroups)): ?>
      <div class="body"><div class="muted">Belum ada grup CC. Klik "📋 Grup CC" untuk membuat grup CC baru.</div></div>
    <?php else: ?>
      <div class="card-toolbar">
        <input type="text" class="search-input" id="searchGrupCC" placeholder="🔍 Cari grup CC..." oninput="filterTable('GrupCC')">
        <div class="info-badge">Total: <strong id="countGrupCC"><?= count($ccGroups) ?></strong> grup CC</div>
      </div>
      <div class="table-scroll">
        <table class="table" id="tableGrupCC">
          <thead>
            <tr>
              <th>Nama Grup CC</th>
              <th>Jumlah Anggota</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($ccGroups as $cg): ?>
            <tr>
              <td><?= e((string)$cg['name']) ?></td>
              <td><span class="badge"><?= (int)$cg['members'] ?> anggota</span></td>
              <td>
                <button class="btn secondary" type="button" style="padding:0.5rem 0.875rem;font-size:0.85rem;" onclick="openCCGroupModal(<?= (int)$cg['id'] ?>)">✏️ Edit</button>
                <button class="btn danger" type="button" style="padding:0.5rem 0.875rem;font-size:0.85rem;" onclick="deleteCCGroup(<?= (int)$cg['id'] ?>, '<?= addslashes(e((string)$cg['name'])) ?>')">🗑️ Hapus</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-pagination" id="paginationGrupCC">
        <div style="display:flex;align-items:center;gap:0.5rem;"><span>Tampilkan</span><select id="perPageGrupCC" onchange="filterTable('GrupCC')"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="-1">Semua</option></select><span>baris</span></div>
        <div class="page-info" id="pageInfoGrupCC"></div>
        <div class="page-btns" id="pageBtnsGrupCC"></div>
      </div>
    <?php endif; ?>
    </div>
  </div>

  <div class="card card-collapsible" id="cardKontak">
    <h3 onclick="toggleCardSlider('cardKontak')">
      <span>📊 Daftar Kontak</span>
      <span class="slider-hint">Klik untuk membuka</span>
      <span class="toggle-icon">▼</span>
    </h3>
    <div class="card-slider">
    <div class="card-toolbar">
      <input type="text" class="search-input" id="searchKontak" placeholder="🔍 Cari nama atau email..." oninput="filterTable('Kontak')">
      <div class="info-badge">Total: <strong id="countKontak"><?= count($rows) ?></strong> kontak</div>
    </div>
    <div class="table-scroll">
      <table class="table" id="tableKontak">
        <thead>
          <tr><th>Nama</th><th>Email</th><th>Sumber</th><th>Last Sync</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e((string)$r['display_name']) ?></td>
            <td><?= e((string)$r['email']) ?></td>
            <td><?= e((string)$r['source']) ?></td>
            <td><?= e((string)$r['last_synced']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-pagination" id="paginationKontak">
      <div style="display:flex;align-items:center;gap:0.5rem;"><span>Tampilkan</span><select id="perPageKontak" onchange="filterTable('Kontak')"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="-1">Semua</option></select><span>baris</span></div>
      <div class="page-info" id="pageInfoKontak"></div>
      <div class="page-btns" id="pageBtnsKontak"></div>
    </div>
    </div>
  </div>
</main>

<!-- Group Modal Drawer -->
<div id="groupModal" class="drawer-overlay">
  <div class="drawer">
    <div class="drawer-header">
      <h2 class="drawer-title">Kelola Anggota Grup</h2>
    </div>
    <div class="drawer-body">
      <form id="modalGroupForm" method="post">
        <input type="hidden" name="group_id" id="groupId" />
        <input type="hidden" id="contactListJson" />
        
        <div style="margin-bottom:1.25rem;">
          <label style="display:block;font-weight:700;color:#0052CC;margin-bottom:0.5rem;">Nama Grup</label>
          <input class="input" type="text" name="group_name" id="groupName" placeholder="Nama grup, misal: Vendor ASAHIMAS" required />
        </div>

        <div style="margin-bottom:1.25rem;">
          <label style="display:block;font-weight:700;color:#0052CC;margin-bottom:0.5rem;">Cari Kontak</label>
          <input class="input" type="text" id="contactSearch" placeholder="Cari nama atau email..." />
        </div>

        <div style="margin-bottom:1.25rem;display:flex;gap:0.5rem;">
          <button class="btn secondary" type="button" onclick="toggleSelectAll(true)" style="flex:1;">✓ Pilih Semua</button>
          <button class="btn secondary" type="button" onclick="toggleSelectAll(false)" style="flex:1;">✗ Bersihkan</button>
        </div>

        <div style="margin-bottom:1.25rem;padding:0.75rem;background:#F5F8FF;border:1px solid #E3F2FD;border-radius:0.6rem;">
          <span style="color:#0052CC;font-weight:700;">Dipilih: </span>
          <span class="contact-counter" id="memberCount">0</span>
        </div>

        <div style="font-size:0.85rem;color:#616161;margin-bottom:1rem;font-weight:500;">Centang kontak yang akan dimasukkan ke grup:</div>

        <div id="contactList" style="max-height:400px;overflow-y:auto;border:1px solid #E8E8E8;border-radius:0.6rem;padding:0.75rem;-webkit-overflow-scrolling:touch;">
          <!-- Contact items will be populated here -->
        </div>

        <input type="hidden" name="create_group" id="createGroupInput" value="0" />
        <input type="hidden" name="update_group" id="updateGroupInput" value="0" />
      </form>
    </div>
    <div class="drawer-footer">
      <button class="btn secondary" type="button" onclick="closeGroupModal()">Batal</button>
      <button class="btn success" type="button" onclick="saveGroupMembers()">Simpan Grup</button>
    </div>
  </div>
</div>

<!-- Group Order Modal Drawer -->
<div id="groupOrderModal" class="drawer-overlay">
  <div class="drawer">
    <div class="drawer-header">
      <h2 class="drawer-title">Kelola Grup Order</h2>
    </div>
    <div class="drawer-body">
      <form id="modalGroupOrderForm" method="post">
        <input type="hidden" name="group_order_id" id="groupOrderId" />
        
        <div style="margin-bottom:1.25rem;">
          <label style="display:block;font-weight:700;color:#0052CC;margin-bottom:0.5rem;">Nama Grup Order</label>
          <input class="input" type="text" name="group_order_name" id="groupOrderName" placeholder="Nama grup order, misal: Order Batch 1" required />
        </div>

        <div style="margin-bottom:1.25rem;">
          <label style="display:block;font-weight:700;color:#0052CC;margin-bottom:0.5rem;">Cari Grup</label>
          <input class="input" type="text" id="groupSearch" placeholder="Cari nama grup..." />
        </div>

        <div style="margin-bottom:1.25rem;display:flex;gap:0.5rem;">
          <button class="btn secondary" type="button" onclick="toggleSelectAllGroups(true)" style="flex:1;">✓ Pilih Semua</button>
          <button class="btn secondary" type="button" onclick="toggleSelectAllGroups(false)" style="flex:1;">✗ Bersihkan</button>
        </div>

        <div style="margin-bottom:1.25rem;padding:0.75rem;background:#F5F8FF;border:1px solid #E3F2FD;border-radius:0.6rem;">
          <span style="color:#0052CC;font-weight:700;">Dipilih: </span>
          <span class="contact-counter" id="groupOrderCount">0</span>
        </div>

        <div style="font-size:0.85rem;color:#616161;margin-bottom:1rem;font-weight:500;">Centang grup yang akan dimasukkan ke grup order:</div>

        <div id="groupList" style="max-height:400px;overflow-y:auto;border:1px solid #E8E8E8;border-radius:0.6rem;padding:0.75rem;-webkit-overflow-scrolling:touch;">
          <!-- Group items will be populated here -->
        </div>

        <input type="hidden" name="create_group_order" id="createGroupOrderInput" value="0" />
      </form>
    </div>
    <div class="drawer-footer">
      <button class="btn secondary" type="button" onclick="closeGroupOrderModal()">Batal</button>
      <button class="btn success" type="button" onclick="saveGroupOrderMembers()">Simpan Grup Order</button>
    </div>
  </div>
</div>

<!-- CC Group Modal Drawer -->
<div id="ccGroupModal" class="drawer-overlay">
  <div class="drawer">
    <div class="drawer-header">
      <h2 class="drawer-title">Kelola Grup CC</h2>
    </div>
    <div class="drawer-body">
      <form id="modalCCGroupForm" method="post">
        <input type="hidden" name="cc_group_id" id="ccGroupId" />

        <div style="margin-bottom:1.25rem;">
          <label style="display:block;font-weight:700;color:#0052CC;margin-bottom:0.5rem;">Nama Grup CC</label>
          <input class="input" type="text" name="cc_group_name" id="ccGroupName" placeholder="Nama grup CC, misal: Finance Team" required />
        </div>

        <div style="margin-bottom:1.25rem;">
          <label style="display:block;font-weight:700;color:#0052CC;margin-bottom:0.5rem;">Cari Kontak</label>
          <input class="input" type="text" id="ccContactSearch" placeholder="Cari nama atau email..." />
        </div>

        <div style="margin-bottom:1.25rem;display:flex;gap:0.5rem;">
          <button class="btn secondary" type="button" onclick="toggleSelectAllCC(true)" style="flex:1;">✓ Pilih Semua</button>
          <button class="btn secondary" type="button" onclick="toggleSelectAllCC(false)" style="flex:1;">✗ Bersihkan</button>
        </div>

        <div style="margin-bottom:1.25rem;padding:0.75rem;background:#F5F8FF;border:1px solid #E3F2FD;border-radius:0.6rem;">
          <span style="color:#0052CC;font-weight:700;">Dipilih: </span>
          <span class="contact-counter" id="ccMemberCount">0</span>
        </div>

        <div style="font-size:0.85rem;color:#616161;margin-bottom:1rem;font-weight:500;">Centang kontak yang akan dimasukkan ke grup CC:</div>

        <div id="ccContactList" style="max-height:400px;overflow-y:auto;border:1px solid #E8E8E8;border-radius:0.6rem;padding:0.75rem;-webkit-overflow-scrolling:touch;">
          <!-- Contact items will be populated here -->
        </div>

        <input type="hidden" name="create_cc_group" value="1" />
      </form>
    </div>
    <div class="drawer-footer">
      <button class="btn secondary" type="button" onclick="closeCCGroupModal()">Batal</button>
      <button class="btn success" type="button" onclick="saveCCGroupMembers()">Simpan Grup CC</button>
    </div>
  </div>
</div>

<!-- Modal Upload Manual Kontak -->
<div id="uploadManualModal" class="modal-overlay">
  <div class="modal" style="max-width:500px;">
    <button type="button" class="close-btn" onclick="closeUploadManualModal()">&times;</button>
    
    <div class="modal-header">
      <h2 class="modal-title">📤 Upload Kontak Manual</h2>
    </div>
    
    <div class="modal-body">
      <!-- Step 1: Template Check -->
      <div id="uploadStep1">
        <div style="background:#E3F2FD;border:1px solid #0052CC;border-radius:6px;padding:16px;margin-bottom:16px;">
          <p style="margin:0 0 12px 0;font-weight:600;color:#0052CC;">Apakah Anda sudah memiliki template CSV?</p>
          <p style="margin:0;color:#666;font-size:14px;">Template CSV berisi header dan contoh data untuk memudahkan Anda menyiapkan data kontak.</p>
        </div>

        <div style="display:flex;gap:12px;">
          <button type="button" class="btn secondary" onclick="downloadTemplate()" style="flex:1;">
            ⬇️ Download Template
          </button>
          <button type="button" class="btn success" onclick="showUploadSection()" style="flex:1;">
            📁 Sudah Ada, Upload File
          </button>
        </div>
      </div>

      <!-- Step 2: Upload -->
      <div id="uploadStep2" style="display:none;">
        <div style="border:2px dashed #0052CC;border-radius:8px;padding:24px;text-align:center;cursor:pointer;background:#F5F8FF;margin-bottom:16px;" id="dropZone">
          <div style="font-size:36px;margin-bottom:8px;">📄</div>
          <p style="margin:0 0 4px 0;font-weight:600;color:#0052CC;">Drag & drop file CSV di sini</p>
          <p style="margin:0;color:#999;font-size:13px;">atau klik untuk browse</p>
          <input type="file" id="csvInput" accept=".csv" style="display:none;" />
        </div>

        <div id="uploadProgress" style="display:none;margin-bottom:16px;">
          <div style="background:#E3F2FD;border:1px solid #0052CC;border-radius:6px;padding:16px;">
            <p style="margin:0;font-weight:600;color:#0052CC;" id="progressText">⏳ Uploading...</p>
            <div style="margin-top:8px;border-radius:4px;height:6px;background:#E8E8E8;overflow:hidden;">
              <div id="progressBar" style="height:100%;background:#0052CC;width:0%;transition:width 0.3s ease;"></div>
            </div>
          </div>
        </div>

        <div id="uploadResult" style="display:none;margin-bottom:16px;">
          <div id="resultContent"></div>
        </div>
      </div>
    </div>
    
    <div class="modal-footer" id="uploadButtons">
      <button type="button" class="btn secondary" onclick="closeUploadManualModal()">Batal</button>
      <button type="button" class="btn secondary" style="display:none;" id="uploadAnotherBtn" onclick="uploadAnother()">Unggah File Lain</button>
      <button type="button" class="btn success" style="display:none;" id="selesaiBtn" onclick="location.reload()">Selesai &amp; Refresh</button>
    </div>
  </div>
</div>

<!-- Modal Manual Update Grup Kontak -->
<div id="manualUpdateModal" class="modal-overlay">
  <div class="modal" style="max-width:500px;">
    <button type="button" class="close-btn" onclick="closeManualUpdateModal()">&times;</button>
    
    <div class="modal-header">
      <h2 class="modal-title">🔄 Manual Update Grup Kontak</h2>
    </div>
    
    <div class="modal-body">
      <div style="background:#E3F2FD;border:1px solid #0052CC;border-radius:6px;padding:16px;margin-bottom:16px;">
        <p style="margin:0 0 12px 0;font-weight:600;color:#0052CC;">Update Anggota Grup</p>
        <p style="margin:0;color:#666;font-size:14px;">Perbarui daftar anggota dan struktur grup kontak secara manual.</p>
      </div>

      <div style="margin-bottom:16px;">
        <label style="display:block;margin-bottom:8px;font-weight:600;color:#0052CC;">Pilih Grup untuk Diupdate:</label>
        <select id="groupSelectionUpdate" class="input" style="width:100%;padding:10px;">
          <option value="">-- Pilih Grup --</option>
        </select>
      </div>

      <div id="updateGroupMembers" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid #E3F2FD;">
        <label style="display:block;margin-bottom:8px;font-weight:600;color:#0052CC;">Anggota Grup Saat Ini:</label>
        <div id="currentGroupMembers" style="border:1px solid #E8E8E8;border-radius:6px;max-height:300px;overflow-y:auto;padding:8px;margin-bottom:16px;">
        </div>
        
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
          <label style="font-weight:600;color:#0052CC;flex:1;margin:0;">Tambah/Update Kontak:</label>
          <button type="button" class="btn" style="padding:0.4rem 0.75rem;font-size:0.8rem;" onclick="toggleSelectAllUpdate(true)">✓ Pilih Semua</button>
          <button type="button" class="btn warn" style="padding:0.4rem 0.75rem;font-size:0.8rem;" onclick="toggleSelectAllUpdate(false)">✕ Bersihkan</button>
        </div>
        <input type="text" id="contactSearchUpdate" class="input" placeholder="Cari kontak..." style="margin-bottom:8px;" />
        <div id="availableContactsList" style="border:1px solid #E8E8E8;border-radius:6px;max-height:300px;overflow-y:auto;padding:8px;">
        </div>
      </div>

      <div id="updateMessage" style="display:none;margin-top:16px;padding:12px;border-radius:6px;text-align:center;">
      </div>
    </div>
    
    <div class="modal-footer">
      <button type="button" class="btn secondary" onclick="closeManualUpdateModal()">Batal</button>
      <button type="button" class="btn success" id="updateGroupBtn" style="display:none;" onclick="submitGroupUpdate()">💾 Simpan Update</button>
    </div>
  </div>
</div>

<script>
// Contact data from PHP
const allContacts = <?= json_encode($rows, JSON_HEX_QUOT | JSON_HEX_TAG) ?>;
const allGroups = <?= json_encode($groups, JSON_HEX_QUOT | JSON_HEX_TAG) ?>;
const allCCGroups = <?= json_encode($ccGroups, JSON_HEX_QUOT | JSON_HEX_TAG) ?>;
let selectedContacts = new Set();
let selectedGroups = new Set();
let editingGroupId = null;
let editingGroupOrderId = null;

// ===== GROUP MODAL FUNCTIONS =====

// Open modal for creating or editing group
async function openGroupModal(groupId = null) {
  editingGroupId = groupId;
  selectedContacts.clear();
  
  const modal = document.getElementById('groupModal');
  modal.classList.add('open');
  
  // Reset form fields
  document.getElementById('groupName').value = '';
  document.getElementById('groupName').removeAttribute('readonly');
  document.getElementById('groupName').removeAttribute('disabled');
  document.getElementById('groupId').value = '';
  document.getElementById('createGroupInput').value = groupId ? '0' : '1';
  document.getElementById('updateGroupInput').value = groupId ? '1' : '0';
  
  renderContactList();
  updateMemberCount();
  
  if (groupId) {
    // Load existing group data via AJAX
    try {
      const response = await fetch(`contacts.php?action=get_group_members&group_id=${groupId}`);
      if (response.ok) {
        const data = await response.json();
        document.getElementById('groupName').value = data.group_name || '';
        document.getElementById('groupId').value = groupId;
        
        // Pre-select current members
        if (data.members && Array.isArray(data.members)) {
          data.members.forEach(memberId => {
            selectedContacts.add(memberId);
          });
        }
        renderContactList();
        updateMemberCount();
      } else {
        console.error('Failed to load group:', response.status);
      }
    } catch (err) {
      console.error('Error loading group:', err);
    }
  }
  
  // Focus on group name field for editing (slight delay to ensure DOM is ready)
  setTimeout(() => {
    const nameField = document.getElementById('groupName');
    if (nameField) {
      nameField.focus();
      // If editing existing group, select all text for easy replacement
      if (groupId) {
        nameField.select();
      }
    }
  }, 100);
}

// Close modal
function closeGroupModal() {
  const modal = document.getElementById('groupModal');
  modal.classList.remove('open');
  selectedContacts.clear();
  editingGroupId = null;
}

// Render contact list with checkboxes
function renderContactList() {
  const searchTerm = document.getElementById('contactSearch').value.toLowerCase();
  const container = document.getElementById('contactList');
  container.innerHTML = '';
  
  allContacts.forEach(contact => {
    const name = contact.display_name || '';
    const email = contact.email || '';
    const searchStr = `${name} ${email}`.toLowerCase();
    
    if (!searchStr.includes(searchTerm)) return;
    
    const label = document.createElement('label');
    label.className = 'contact-item';
    label.style.cursor = 'pointer';
    
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.value = contact.id;
    input.checked = selectedContacts.has(contact.id);
    input.onchange = (e) => {
      if (e.target.checked) {
        selectedContacts.add(contact.id);
      } else {
        selectedContacts.delete(contact.id);
      }
      updateMemberCount();
    };
    
    const nameDiv = document.createElement('div');
    nameDiv.style.flex = '1';
    
    const nameSpan = document.createElement('div');
    nameSpan.className = 'contact-name';
    nameSpan.textContent = name;
    
    const emailSpan = document.createElement('div');
    emailSpan.className = 'contact-email';
    emailSpan.textContent = email;
    
    nameDiv.appendChild(nameSpan);
    nameDiv.appendChild(emailSpan);
    
    label.appendChild(input);
    label.appendChild(nameDiv);
    container.appendChild(label);
  });
}

// Filter contacts by search
document.getElementById('contactSearch')?.addEventListener('input', function() {
  renderContactList();
});

// Toggle select all / deselect all
function toggleSelectAll(selectAll) {
  const searchTerm = document.getElementById('contactSearch').value.toLowerCase();
  
  allContacts.forEach(contact => {
    const name = contact.display_name || '';
    const email = contact.email || '';
    const searchStr = `${name} ${email}`.toLowerCase();
    
    if (!searchStr.includes(searchTerm)) return;
    
    if (selectAll) {
      selectedContacts.add(contact.id);
    } else {
      selectedContacts.delete(contact.id);
    }
  });
  
  renderContactList();
  updateMemberCount();
}

// Update member count display
function updateMemberCount() {
  const count = selectedContacts.size;
  document.getElementById('memberCount').textContent = count;
}

// Save group members
function saveGroupMembers() {
  const groupName = document.getElementById('groupName').value.trim();
  
  if (!groupName) {
    Swal.fire('Peringatan', 'Nama grup tidak boleh kosong', 'warning');
    return;
  }
  
  if (selectedContacts.size === 0) {
    Swal.fire('Peringatan', 'Pilih minimal 1 kontak untuk grup', 'warning');
    return;
  }
  
  // Disable button while processing
  const saveBtn = event.target;
  const originalText = saveBtn.textContent;
  saveBtn.disabled = true;
  saveBtn.textContent = '⏳ Menyimpan...';
  
  // Prepare form data
  const form = document.getElementById('modalGroupForm');
  const formData = new FormData(form);
  
  // Add selected contacts
  selectedContacts.forEach(contactId => {
    formData.append('contact_id[]', contactId);
  });
  
  // Submit form
  const hiddenForm = document.createElement('form');
  hiddenForm.method = 'POST';
  hiddenForm.action = 'contacts.php';
  
  for (let [key, value] of formData) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = value;
    hiddenForm.appendChild(input);
  }
  
  document.body.appendChild(hiddenForm);
  
  // Show loading state
  Swal.fire({
    icon: 'info',
    title: 'Menyimpan...',
    text: 'Sistem sedang menyimpan perubahan grup',
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  // Submit form (will redirect page and reload)
  hiddenForm.submit();
}

// Delete group
async function deleteGroup(groupId, groupName) {
  const result = await Swal.fire({
    title: 'Hapus Grup?',
    html: `<p style="color: #666; font-size: 14px;">Anda akan menghapus grup <strong>${groupName}</strong> dan semua anggotanya.</p><p style="color: #dc3545; font-weight: bold;">⚠️ Tindakan ini TIDAK DAPAT DIBATALKAN!</p>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    confirmButtonColor: '#E74C3C',
    cancelButtonText: 'Batal',
    cancelButtonColor: '#6b7280',
    allowOutsideClick: false,
    allowEscapeKey: false
  });
  
  if (result.isConfirmed) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'contacts.php';
    
    const groupIdInput = document.createElement('input');
    groupIdInput.type = 'hidden';
    groupIdInput.name = 'delete_group';
    groupIdInput.value = groupId;
    
    form.appendChild(groupIdInput);
    document.body.appendChild(form);
    form.submit();
  }
}

// Close modal when clicking outside
document.getElementById('groupModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeGroupModal();
  }
});

// ===== GROUP ORDER MODAL FUNCTIONS =====

// Open modal for creating or editing group order
async function openGroupOrderModal(groupOrderId = null) {
  editingGroupOrderId = groupOrderId;
  selectedGroups.clear();
  
  const modal = document.getElementById('groupOrderModal');
  modal.classList.add('open');
  
  document.getElementById('groupOrderName').value = '';
  document.getElementById('groupOrderId').value = '';
  document.getElementById('createGroupOrderInput').value = groupOrderId ? '0' : '1';
  
  renderGroupList();
  updateGroupOrderCount();
  
  if (groupOrderId) {
    // Load existing group order data via AJAX
    try {
      const response = await fetch(`contacts.php?action=get_group_order_items&group_order_id=${groupOrderId}`);
      if (response.ok) {
        const data = await response.json();
        document.getElementById('groupOrderName').value = data.order_name || '';
        document.getElementById('groupOrderId').value = groupOrderId;
        
        // Pre-select current groups
        if (data.groups && Array.isArray(data.groups)) {
          data.groups.forEach(groupId => {
            selectedGroups.add(groupId);
          });
        }
        renderGroupList();
        updateGroupOrderCount();
      }
    } catch (err) {
      console.error('Error loading group order:', err);
    }
  }
  
  // Focus on group order name
  document.getElementById('groupOrderName').focus();
}

// Close group order modal
function closeGroupOrderModal() {
  const modal = document.getElementById('groupOrderModal');
  modal.classList.remove('open');
  selectedGroups.clear();
  editingGroupOrderId = null;
}

// Render group list with checkboxes
function renderGroupList() {
  const searchTerm = document.getElementById('groupSearch').value.toLowerCase();
  const container = document.getElementById('groupList');
  container.innerHTML = '';
  
  allGroups.forEach(group => {
    const name = group.name || '';
    const searchStr = name.toLowerCase();
    
    if (!searchStr.includes(searchTerm)) return;
    
    const label = document.createElement('label');
    label.className = 'contact-item';
    label.style.cursor = 'pointer';
    
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.value = group.id;
    input.checked = selectedGroups.has(parseInt(group.id));
    input.onchange = (e) => {
      if (e.target.checked) {
        selectedGroups.add(parseInt(group.id));
      } else {
        selectedGroups.delete(parseInt(group.id));
      }
      updateGroupOrderCount();
    };
    
    const nameDiv = document.createElement('div');
    nameDiv.style.flex = '1';
    
    const nameSpan = document.createElement('div');
    nameSpan.className = 'contact-name';
    nameSpan.textContent = name;
    
    const memberSpan = document.createElement('div');
    memberSpan.className = 'contact-email';
    memberSpan.textContent = group.members + ' anggota';
    
    nameDiv.appendChild(nameSpan);
    nameDiv.appendChild(memberSpan);
    
    label.appendChild(input);
    label.appendChild(nameDiv);
    container.appendChild(label);
  });
}

// Filter groups by search
document.getElementById('groupSearch')?.addEventListener('input', function() {
  renderGroupList();
});

// Toggle select all / deselect all for groups
function toggleSelectAllGroups(selectAll) {
  const searchTerm = document.getElementById('groupSearch').value.toLowerCase();
  
  allGroups.forEach(group => {
    const name = group.name || '';
    const searchStr = name.toLowerCase();
    
    if (!searchStr.includes(searchTerm)) return;
    
    if (selectAll) {
      selectedGroups.add(parseInt(group.id));
    } else {
      selectedGroups.delete(parseInt(group.id));
    }
  });
  
  renderGroupList();
  updateGroupOrderCount();
}

// Update group order count display
function updateGroupOrderCount() {
  const count = selectedGroups.size;
  document.getElementById('groupOrderCount').textContent = count;
}

// Save group order members
function saveGroupOrderMembers() {
  const orderName = document.getElementById('groupOrderName').value.trim();
  
  if (!orderName) {
    Swal.fire('Peringatan', 'Nama grup order tidak boleh kosong', 'warning');
    return;
  }
  
  if (selectedGroups.size === 0) {
    Swal.fire('Peringatan', 'Pilih minimal 1 grup untuk grup order', 'warning');
    return;
  }
  
  // Prepare form data
  const form = document.getElementById('modalGroupOrderForm');
  const formData = new FormData(form);
  
  // Add selected groups
  selectedGroups.forEach(groupId => {
    formData.append('group_id[]', groupId);
  });
  
  // Submit form
  const hiddenForm = document.createElement('form');
  hiddenForm.method = 'POST';
  hiddenForm.action = 'contacts.php';
  
  for (let [key, value] of formData) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = value;
    hiddenForm.appendChild(input);
  }
  
  document.body.appendChild(hiddenForm);
  hiddenForm.submit();
}

// Delete group order
async function deleteGroupOrder(groupOrderId, orderName) {
  const result = await Swal.fire({
    title: 'Hapus Grup Order?',
    html: `<p style="color: #666; font-size: 14px;">Anda akan menghapus grup order <strong>${orderName}</strong> dan semua grupnya.</p><p style="color: #dc3545; font-weight: bold;">⚠️ Tindakan ini TIDAK DAPAT DIBATALKAN!</p>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    confirmButtonColor: '#E74C3C',
    cancelButtonText: 'Batal',
    cancelButtonColor: '#6b7280',
    allowOutsideClick: false,
    allowEscapeKey: false
  });
  
  if (result.isConfirmed) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'contacts.php';
    
    const groupOrderIdInput = document.createElement('input');
    groupOrderIdInput.type = 'hidden';
    groupOrderIdInput.name = 'delete_group_order';
    groupOrderIdInput.value = groupOrderId;
    
    form.appendChild(groupOrderIdInput);
    document.body.appendChild(form);
    form.submit();
  }
}

// Close modal when clicking outside
document.getElementById('groupOrderModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeGroupOrderModal();
  }
});

// Existing code - Select all / Clear
document.getElementById('btnSelectAll')?.addEventListener('click', function(){
  document.querySelectorAll('input[type=checkbox][name="contact_id[]"]').forEach(ch => ch.checked = true);
});
document.getElementById('btnClear')?.addEventListener('click', function(){
  document.querySelectorAll('input[type=checkbox][name="contact_id[]"]').forEach(ch => ch.checked = false);
});

// Confirm clear contacts dengan SweetAlert2
async function confirmClearContacts() {
  const result = await Swal.fire({
    title: 'Bersihkan History Kontak?',
    html: '<p style="color: #666; font-size: 14px;">Anda akan menghapus <strong>SEMUA kontak</strong> dari database.</p><p style="color: #dc3545; font-weight: bold;">⚠️ Tindakan ini TIDAK DAPAT DIBATALKAN!</p>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus Semua',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Batal',
    cancelButtonColor: '#6b7280',
    allowOutsideClick: false,
    allowEscapeKey: false
  });
  
  if (result.isConfirmed) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'contacts.php';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'clear_contacts';
    input.value = '1';
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
  }
}

// ===== UPLOAD MANUAL KONTAK FUNCTIONS =====

function openUploadManualModal() {
  document.getElementById('uploadManualModal').classList.add('open');
  document.getElementById('uploadStep1').style.display = 'block';
  document.getElementById('uploadStep2').style.display = 'none';
  document.getElementById('uploadProgress').style.display = 'none';
  document.getElementById('uploadResult').style.display = 'none';
  document.getElementById('csvInput').value = '';
}

function closeUploadManualModal() {
  document.getElementById('uploadManualModal').classList.remove('open');
}

function downloadTemplate() {
  // Navigate to download endpoint
  window.location.href = 'api_contact_upload.php?action=download_template';
}

function showUploadSection() {
  document.getElementById('uploadStep1').style.display = 'none';
  document.getElementById('uploadStep2').style.display = 'block';
  document.getElementById('uploadButtons').style.display = 'flex';
  
  // Setup drag and drop
  setupDragDrop();
}

function setupDragDrop() {
  const dropZone = document.getElementById('dropZone');
  const csvInput = document.getElementById('csvInput');
  
  // Click to select file
  dropZone.addEventListener('click', () => csvInput.click());
  
  // File selected
  csvInput.addEventListener('change', (e) => {
    if (e.target.files && e.target.files[0]) {
      uploadFile(e.target.files[0]);
    }
  });
  
  // Drag and drop
  dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.style.borderColor = '#0052CC';
    dropZone.style.background = '#E3F2FD';
  });
  
  dropZone.addEventListener('dragleave', () => {
    dropZone.style.borderColor = '#0052CC';
    dropZone.style.background = '#F5F8FF';
  });
  
  dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      uploadFile(e.dataTransfer.files[0]);
    }
  });
}

function uploadFile(file) {
  // Validate file
  if (!file.name.toLowerCase().endsWith('.csv')) {
    Swal.fire('Error', 'File harus berformat CSV', 'error');
    return;
  }
  
  if (file.size > 10 * 1024 * 1024) {
    Swal.fire('Error', 'File terlalu besar (maks 10MB)', 'error');
    return;
  }
  
  // Show progress
  document.getElementById('uploadProgress').style.display = 'block';
  document.getElementById('uploadResult').style.display = 'none';
  
  // Hide all footer buttons during upload
  document.querySelectorAll('#uploadButtons button').forEach(btn => btn.style.display = 'none');
  
  // Prepare form data
  const formData = new FormData();
  formData.append('contact_file', file);
  
  // Upload
  fetch('api_contact_upload.php', {
    method: 'POST',
    body: formData
  })
  .then(response => {
    if (!response.ok) {
      return response.json().then(data => {
        throw new Error(data.error || 'Upload failed');
      });
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      showUploadResult(data.summary);
    } else {
      throw new Error(data.error || 'Unknown error');
    }
  })
  .catch(error => {
    console.error('Upload error:', error);
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('uploadResult').style.display = 'block';
    document.getElementById('resultContent').innerHTML = '<div style="background:#FADDD1;border:1px solid #E74C3C;border-radius:6px;padding:16px;color:#7F4028;"><p style="margin:0;font-weight:600;">❌ Upload Gagal</p><p style="margin:8px 0 0 0;font-size:14px;">' + error.message + '</p></div>';
    
    // Show buttons - cancel visible by default, show retry option
    document.querySelectorAll('#uploadButtons button').forEach(btn => {
      if (btn.textContent.includes('Batal')) {
        btn.style.display = 'inline-flex';
      } else if (btn.textContent.includes('Unggah')) {
        btn.style.display = 'inline-flex';
      } else {
        btn.style.display = 'none';
      }
    });
  });
}

function showUploadResult(summary) {
  document.getElementById('uploadProgress').style.display = 'none';
  document.getElementById('uploadResult').style.display = 'block';
  
  let resultHtml = '<div style="background:#E3F2FD;border:1px solid #0052CC;border-radius:6px;padding:16px;color:#0052CC;">';
  resultHtml += '<p style="margin:0;font-weight:600;">✅ Upload Berhasil!</p>';
  resultHtml += '<table style="width:100%;margin-top:12px;font-size:14px;">';
  resultHtml += '<tr><td style="padding:4px 0;">Diproses:</td><td style="text-align:right;font-weight:600;">' + summary.processed + ' baris</td></tr>';
  resultHtml += '<tr><td style="padding:4px 0;">Ditambahkan:</td><td style="text-align:right;color:#28a745;font-weight:600;">' + summary.inserted + ' kontak</td></tr>';
  
  if (summary.skipped_duplicate > 0) {
    resultHtml += '<tr><td style="padding:4px 0;">Skip (duplicate):</td><td style="text-align:right;color:#fd7e14;font-weight:600;">' + summary.skipped_duplicate + ' email</td></tr>';
  }
  
  if (summary.skipped_empty > 0) {
    resultHtml += '<tr><td style="padding:4px 0;">Skip (kosong):</td><td style="text-align:right;color:#fd7e14;font-weight:600;">' + summary.skipped_empty + ' baris</td></tr>';
  }
  
  if (summary.errors > 0) {
    resultHtml += '<tr><td style="padding:4px 0;">Error:</td><td style="text-align:right;color:#E74C3C;font-weight:600;">' + summary.errors + ' item</td></tr>';
  }
  
  resultHtml += '</table>';
  
  if (summary.notes && summary.notes.length > 0) {
    resultHtml += '<div style="margin-top:12px;border-top:1px solid rgba(0,82,204,0.2);padding-top:12px;font-size:13px;">';
    summary.notes.forEach(note => {
      resultHtml += '<p style="margin:4px 0;">' + note + '</p>';
    });
    resultHtml += '</div>';
  }
  
  resultHtml += '</div>';
  
  document.getElementById('resultContent').innerHTML = resultHtml;
  
  // Show success buttons
  document.querySelectorAll('#uploadButtons button').forEach(btn => btn.style.display = 'none');
  document.getElementById('uploadAnotherBtn').style.display = 'inline-flex';
  document.getElementById('selesaiBtn').style.display = 'inline-flex';
}

function uploadAnother() {
  document.getElementById('csvInput').value = '';
  document.getElementById('uploadResult').style.display = 'none';
  document.getElementById('uploadProgress').style.display = 'none';
  
  // Reset to upload section with only cancel button visible
  document.querySelectorAll('#uploadButtons button').forEach(btn => {
    if (btn.textContent.includes('Batal')) {
      btn.style.display = 'inline-flex';
    } else {
      btn.style.display = 'none';
    }
  });
}

// Close modal when clicking outside
document.getElementById('uploadManualModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeUploadManualModal();
  }
});

// ===== MANUAL UPDATE GROUP FUNCTIONS =====

let selectedContactsForUpdate = new Set();

function openManualUpdateModal() {
  const modal = document.getElementById('manualUpdateModal');
  modal.classList.add('open');
  
  selectedContactsForUpdate.clear();
  document.getElementById('updateGroupMembers').style.display = 'none';
  document.getElementById('updateGroupBtn').style.display = 'none';
  document.getElementById('updateMessage').style.display = 'none';
  document.getElementById('contactSearchUpdate').value = '';
  
  // Populate group selection dropdown
  populateGroupsDropdown();
}

function closeManualUpdateModal() {
  const modal = document.getElementById('manualUpdateModal');
  modal.classList.remove('open');
  selectedContactsForUpdate.clear();
}

function populateGroupsDropdown() {
  const dropdown = document.getElementById('groupSelectionUpdate');
  dropdown.innerHTML = '<option value="">-- Pilih Grup --</option>';
  
  allGroups.forEach(group => {
    const option = document.createElement('option');
    option.value = group.id;
    option.textContent = group.name + ' (' + group.members + ' anggota)';
    dropdown.appendChild(option);
  });
  
  dropdown.addEventListener('change', function() {
    if (this.value) {
      loadGroupMembers(this.value);
    } else {
      document.getElementById('updateGroupMembers').style.display = 'none';
      document.getElementById('updateGroupBtn').style.display = 'none';
      selectedContactsForUpdate.clear();
    }
  });
}

function loadGroupMembers(groupId) {
  selectedContactsForUpdate.clear();
  
  fetch(`contacts.php?action=get_group_members&group_id=${groupId}`)
    .then(response => {
      if (!response.ok) throw new Error('Failed to load group members');
      return response.json();
    })
    .then(data => {
      if (data.members) {
        data.members.forEach(member => {
          selectedContactsForUpdate.add(member.contact_id);
        });
      }
      
      document.getElementById('updateGroupMembers').style.display = 'block';
      document.getElementById('updateGroupBtn').style.display = 'inline-flex';
      
      renderCurrentGroupMembers();
      renderAvailableContacts();
    })
    .catch(error => {
      console.error('Error:', error);
      Swal.fire('Error', 'Gagal memuat anggota grup', 'error');
    });
}

function renderCurrentGroupMembers() {
  const container = document.getElementById('currentGroupMembers');
  container.innerHTML = '';
  
  if (selectedContactsForUpdate.size === 0) {
    container.innerHTML = '<div style="text-align:center;color:#999;padding:12px;">Belum ada anggota</div>';
    return;
  }
  
  selectedContactsForUpdate.forEach(contactId => {
    const contact = allContacts.find(c => c.id == contactId);
    if (contact) {
      const item = document.createElement('div');
      item.style.cssText = 'padding:8px;background:#F5F8FF;border-radius:4px;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;';
      
      const nameDiv = document.createElement('div');
      nameDiv.innerHTML = `<div style="font-weight:600;color:#0052CC;">${contact.display_name}</div><div style="font-size:12px;color:#666;">${contact.email}</div>`;
      
      const removeBtn = document.createElement('button');
      removeBtn.className = 'btn danger';
      removeBtn.style.cssText = 'padding:0.4rem 0.75rem;font-size:0.8rem;';
      removeBtn.textContent = '✕';
      removeBtn.onclick = () => {
        selectedContactsForUpdate.delete(contactId);
        renderCurrentGroupMembers();
      };
      
      item.appendChild(nameDiv);
      item.appendChild(removeBtn);
      container.appendChild(item);
    }
  });
}

function renderAvailableContacts() {
  const searchTerm = document.getElementById('contactSearchUpdate').value.toLowerCase();
  const container = document.getElementById('availableContactsList');
  container.innerHTML = '';
  
  const filtered = allContacts.filter(contact => {
    if (selectedContactsForUpdate.has(contact.id)) return false;
    const searchStr = `${contact.display_name} ${contact.email}`.toLowerCase();
    return searchStr.includes(searchTerm);
  });
  
  if (filtered.length === 0) {
    container.innerHTML = '<div style="text-align:center;color:#999;padding:12px;">Tidak ada kontak tersedia</div>';
    return;
  }
  
  filtered.forEach(contact => {
    const item = document.createElement('div');
    item.style.cssText = 'padding:8px;background:#F9FAFB;border-radius:4px;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;cursor:pointer;';
    item.onmouseover = () => item.style.background = '#E3F2FD';
    item.onmouseout = () => item.style.background = '#F9FAFB';
    
    const nameDiv = document.createElement('div');
    nameDiv.innerHTML = `<div style="font-weight:600;color:#0052CC;">${contact.display_name}</div><div style="font-size:12px;color:#666;">${contact.email}</div>`;
    
    const addBtn = document.createElement('button');
    addBtn.className = 'btn success';
    addBtn.style.cssText = 'padding:0.4rem 0.75rem;font-size:0.8rem;';
    addBtn.textContent = '✓';
    addBtn.onclick = () => {
      selectedContactsForUpdate.add(contact.id);
      renderCurrentGroupMembers();
      renderAvailableContacts();
    };
    
    item.appendChild(nameDiv);
    item.appendChild(addBtn);
    container.appendChild(item);
  });
}

document.getElementById('contactSearchUpdate')?.addEventListener('input', function() {
  renderAvailableContacts();
});

function toggleSelectAllUpdate(selectAll) {
  const searchTerm = document.getElementById('contactSearchUpdate').value.toLowerCase();
  
  allContacts.forEach(contact => {
    // Skip already selected contacts when select all
    if (selectAll && selectedContactsForUpdate.has(contact.id)) return;
    
    const name = contact.display_name || '';
    const email = contact.email || '';
    const searchStr = `${name} ${email}`.toLowerCase();
    
    // If searching, only affect searched items
    if (searchTerm && !searchStr.includes(searchTerm)) return;
    
    if (selectAll) {
      selectedContactsForUpdate.add(contact.id);
    } else {
      selectedContactsForUpdate.delete(contact.id);
    }
  });
  
  renderCurrentGroupMembers();
  renderAvailableContacts();
}

function submitGroupUpdate() {
  const groupId = document.getElementById('groupSelectionUpdate').value;
  
  if (!groupId) {
    Swal.fire('Peringatan', 'Pilih grup terlebih dahulu', 'warning');
    return;
  }
  
  if (selectedContactsForUpdate.size === 0) {
    Swal.fire('Peringatan', 'Pilih minimal 1 kontak untuk grup', 'warning');
    return;
  }
  
  // Get group name from allGroups
  const group = allGroups.find(g => g.id == groupId);
  const groupName = group ? group.name : '';
  
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'contacts.php';
  
  const inputs = {
    'update_group': '1',
    'group_id': groupId,
    'group_name': groupName
  };
  
  for (let [key, value] of Object.entries(inputs)) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = value;
    form.appendChild(input);
  }
  
  selectedContactsForUpdate.forEach(contactId => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'contact_id[]';
    input.value = contactId;
    form.appendChild(input);
  });
  
  document.body.appendChild(form);
  
  Swal.fire({
    icon: 'info',
    title: 'Menyimpan...',
    text: 'Sistem sedang menyimpan perubahan grup',
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  form.submit();
}

document.getElementById('manualUpdateModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeManualUpdateModal();
  }
});

// ===== CC GROUP MODAL FUNCTIONS =====

let selectedCCContacts = new Set();
let editingCCGroupId = null;

async function openCCGroupModal(ccGroupId = null) {
  editingCCGroupId = ccGroupId;
  selectedCCContacts.clear();

  const modal = document.getElementById('ccGroupModal');
  modal.classList.add('open');

  document.getElementById('ccGroupName').value = '';
  document.getElementById('ccGroupId').value = '';

  renderCCContactList();
  updateCCMemberCount();

  if (ccGroupId) {
    try {
      const response = await fetch(`contacts.php?action=get_cc_group_members&cc_group_id=${ccGroupId}`);
      if (response.ok) {
        const data = await response.json();
        document.getElementById('ccGroupName').value = data.cc_group_name || '';
        document.getElementById('ccGroupId').value = ccGroupId;
        if (Array.isArray(data.member_ids)) {
          data.member_ids.forEach(id => selectedCCContacts.add(id));
        }
        renderCCContactList();
        updateCCMemberCount();
      }
    } catch (err) {
      console.error('Error loading CC group:', err);
    }
  }

  setTimeout(() => {
    const nameField = document.getElementById('ccGroupName');
    if (nameField) { nameField.focus(); if (ccGroupId) nameField.select(); }
  }, 100);
}

function closeCCGroupModal() {
  document.getElementById('ccGroupModal').classList.remove('open');
  selectedCCContacts.clear();
  editingCCGroupId = null;
}

function renderCCContactList() {
  const searchTerm = document.getElementById('ccContactSearch').value.toLowerCase();
  const container = document.getElementById('ccContactList');
  container.innerHTML = '';

  allContacts.forEach(contact => {
    const name = contact.display_name || '';
    const email = contact.email || '';
    if (!`${name} ${email}`.toLowerCase().includes(searchTerm)) return;

    const label = document.createElement('label');
    label.className = 'contact-item';
    label.style.cursor = 'pointer';

    const input = document.createElement('input');
    input.type = 'checkbox';
    input.value = contact.id;
    input.checked = selectedCCContacts.has(contact.id);
    input.onchange = (e) => {
      if (e.target.checked) selectedCCContacts.add(contact.id);
      else selectedCCContacts.delete(contact.id);
      updateCCMemberCount();
    };

    const nameDiv = document.createElement('div');
    nameDiv.style.flex = '1';
    const nameSpan = document.createElement('div');
    nameSpan.className = 'contact-name';
    nameSpan.textContent = name;
    const emailSpan = document.createElement('div');
    emailSpan.className = 'contact-email';
    emailSpan.textContent = email;
    nameDiv.appendChild(nameSpan);
    nameDiv.appendChild(emailSpan);

    label.appendChild(input);
    label.appendChild(nameDiv);
    container.appendChild(label);
  });
}

document.getElementById('ccContactSearch')?.addEventListener('input', renderCCContactList);

function toggleSelectAllCC(selectAll) {
  const searchTerm = document.getElementById('ccContactSearch').value.toLowerCase();
  allContacts.forEach(contact => {
    const searchStr = `${contact.display_name || ''} ${contact.email || ''}`.toLowerCase();
    if (!searchStr.includes(searchTerm)) return;
    if (selectAll) selectedCCContacts.add(contact.id);
    else selectedCCContacts.delete(contact.id);
  });
  renderCCContactList();
  updateCCMemberCount();
}

function updateCCMemberCount() {
  document.getElementById('ccMemberCount').textContent = selectedCCContacts.size;
}

function saveCCGroupMembers() {
  const groupName = document.getElementById('ccGroupName').value.trim();
  if (!groupName) {
    Swal.fire('Peringatan', 'Nama grup CC tidak boleh kosong', 'warning');
    return;
  }
  if (selectedCCContacts.size === 0) {
    Swal.fire('Peringatan', 'Pilih minimal 1 kontak untuk grup CC', 'warning');
    return;
  }

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'contacts.php';

  const fields = {
    'create_cc_group': '1',
    'cc_group_name': groupName,
    'cc_group_id': document.getElementById('ccGroupId').value || '0'
  };
  for (const [key, val] of Object.entries(fields)) {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = key; inp.value = val;
    form.appendChild(inp);
  }
  selectedCCContacts.forEach(cid => {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'cc_contact_id[]'; inp.value = cid;
    form.appendChild(inp);
  });
  document.body.appendChild(form);
  form.submit();
}

async function deleteCCGroup(ccGroupId, groupName) {
  const result = await Swal.fire({
    title: 'Hapus Grup CC?',
    html: `<p style="color:#666;font-size:14px;">Anda akan menghapus grup CC <strong>${groupName}</strong>.</p><p style="color:#dc3545;font-weight:bold;">⚠️ Tindakan ini TIDAK DAPAT DIBATALKAN!</p>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    confirmButtonColor: '#E74C3C',
    cancelButtonText: 'Batal',
    cancelButtonColor: '#6b7280',
    allowOutsideClick: false,
    allowEscapeKey: false
  });
  if (result.isConfirmed) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'contacts.php';
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'delete_cc_group'; inp.value = ccGroupId;
    form.appendChild(inp);
    document.body.appendChild(form);
    form.submit();
  }
}

document.getElementById('ccGroupModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeCCGroupModal();
});

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
  localStorage.setItem('card_' + cardId, card.classList.contains('collapsed') ? '1' : '0');
}
// Restore state from localStorage
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.card-collapsible').forEach(function(card) {
    var saved = localStorage.getItem('card_' + card.id);
    if (saved === '0') card.classList.remove('collapsed');
    else if (saved === '1') card.classList.add('collapsed');
  });
});

// ===== DYNAMIC CARD TABLE: FILTER + PAGINATION =====
const _cardState = {};

function initCardTable(key) {
  const table = document.getElementById('table' + key);
  if (!table) return;
  const rows = Array.from(table.tBodies[0].rows);
  _cardState[key] = { allRows: rows, filtered: rows, page: 1 };
  filterTable(key);
}

function filterTable(key) {
  const state = _cardState[key];
  if (!state) return;
  const searchEl = document.getElementById('search' + key);
  const q = searchEl ? searchEl.value.toLowerCase().trim() : '';

  state.filtered = state.allRows.filter(function(row) {
    if (!q) return true;
    return row.textContent.toLowerCase().indexOf(q) !== -1;
  });
  state.page = 1;

  const countEl = document.getElementById('count' + key);
  if (countEl) {
    countEl.textContent = state.filtered.length;
  }
  renderPage(key);
}

function renderPage(key) {
  const state = _cardState[key];
  if (!state) return;
  const perPageEl = document.getElementById('perPage' + key);
  let perPage = perPageEl ? parseInt(perPageEl.value) : 10;
  const rows = state.filtered;
  const total = rows.length;
  const showAll = perPage === -1;
  const totalPages = showAll ? 1 : Math.max(1, Math.ceil(total / perPage));
  if (state.page > totalPages) state.page = totalPages;
  const start = showAll ? 0 : (state.page - 1) * perPage;
  const end = showAll ? total : Math.min(start + perPage, total);

  // Hide all, show matched slice
  state.allRows.forEach(function(r) { r.style.display = 'none'; });
  for (let i = start; i < end; i++) {
    rows[i].style.display = '';
  }

  // Page info
  const infoEl = document.getElementById('pageInfo' + key);
  if (infoEl) {
    infoEl.textContent = total === 0 ? 'Tidak ada data' : 'Menampilkan ' + (start + 1) + '-' + end + ' dari ' + total;
  }

  // Page buttons
  const btnsEl = document.getElementById('pageBtns' + key);
  if (btnsEl) {
    btnsEl.innerHTML = '';
    if (!showAll && totalPages > 1) {
      // Prev
      var prev = document.createElement('button');
      prev.className = 'page-btn';
      prev.textContent = '\u25c0';
      prev.disabled = state.page <= 1;
      prev.onclick = function() { state.page--; renderPage(key); };
      btnsEl.appendChild(prev);

      // Page numbers (max 7 visible)
      var startP = Math.max(1, state.page - 3);
      var endP = Math.min(totalPages, startP + 6);
      if (endP - startP < 6) startP = Math.max(1, endP - 6);
      for (var p = startP; p <= endP; p++) {
        var btn = document.createElement('button');
        btn.className = 'page-btn' + (p === state.page ? ' active' : '');
        btn.textContent = p;
        btn.onclick = (function(pg) { return function() { state.page = pg; renderPage(key); }; })(p);
        btnsEl.appendChild(btn);
      }

      // Next
      var next = document.createElement('button');
      next.className = 'page-btn';
      next.textContent = '\u25b6';
      next.disabled = state.page >= totalPages;
      next.onclick = function() { state.page++; renderPage(key); };
      btnsEl.appendChild(next);
    }
  }
}

// Initialize all dynamic card tables on load
document.addEventListener('DOMContentLoaded', function() {
  initCardTable('Grup');
  initCardTable('GrupOrder');
  initCardTable('GrupCC');
  initCardTable('Kontak');
});
</script>

<!-- Page Transition Overlay -->
<div class="page-transition" id="pageTransition"></div>

</body>
</html>