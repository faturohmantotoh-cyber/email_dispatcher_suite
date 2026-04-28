<?php
// groups.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';

ensure_dirs();
$pdo = DB::conn();

//
// --- Utilities ---
//
function ensure_group_tables(PDO $pdo) {
    // pastikan tabel contacts sudah punya unique key di email (disarankan)
    // (Tidak dipaksa di sini, tapi diasumsikan sudah disiapkan)

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_groups_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_members (
            group_id INT NOT NULL,
            contact_id INT NOT NULL,
            added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (group_id, contact_id),
            CONSTRAINT fk_gm_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
            CONSTRAINT fk_gm_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function slugify_filename($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) { return 'group'; }
    return $text;
}

function export_group_to_csv(PDO $pdo, int $groupId, string $groupName): array {
    $exportDir = __DIR__ . '/../storage/exports';
    if (!is_dir($exportDir)) {
        @mkdir($exportDir, 0777, true);
    }

    // Ambil anggota grup
    $stmt = $pdo->prepare("
        SELECT c.display_name AS name, c.email, c.source, g.name AS `group`
        FROM group_members gm
        JOIN contacts c ON c.id = gm.contact_id
        JOIN groups g   ON g.id = gm.group_id
        WHERE gm.group_id = ?
        ORDER BY c.display_name, c.email
    ");
    $stmt->execute([$groupId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $slug = slugify_filename($groupName);
    $ts   = date('Ymd_His');
    $file = "{$exportDir}/group_{$slug}_{$ts}.csv";

    // Tulis CSV (dengan BOM agar nyaman di Excel)
    $fp = fopen($file, 'w');
    if (!$fp) {
        return ['ok' => false, 'msg' => "Gagal membuat file CSV: $file"];
    }
    // UTF-8 BOM
    fwrite($fp, "\xEF\xBB\xBF");

    // Header
    fputcsv($fp, ['Name','Email','Source','Group','ExportedAt']);
    $exportedAt = date('c');

    $count = 0;
    if (!empty($rows)) {
        foreach ($rows as $r) {
            fputcsv($fp, [
                (string)($r['name'] ?? ''),
                (string)($r['email'] ?? ''),
                (string)($r['source'] ?? ''),
                (string)($r['group'] ?? $groupName),
                $exportedAt
            ]);
            $count++;
        }
    }
    fclose($fp);

    $bytes = @filesize($file);
    if ($bytes === false) $bytes = 0;

    return [
        'ok'    => true,
        'count' => $count,
        'bytes' => $bytes,
        'path'  => $file,
        'href'  => str_replace(__DIR__ . '/..', '', $file) // buat relative URL (mis: /storage/exports/xxx.csv)
    ];
}

//
// --- Bootstrap & actions ---
//
ensure_group_tables($pdo);

$msg = '';      // HTML message (success/warn/error)
$severity = ''; // 'ok' | 'warn' | 'err'

if (isset($_POST['export_group'])) {
    $gid = (int)($_POST['group_id'] ?? 0);
    if ($gid <= 0) {
        $severity = 'err';
        $msg = '✗ <strong>Parameter group_id tidak valid.</strong>';
    } else {
        // validasi grup ada
        $gname = $pdo->prepare("SELECT name FROM groups WHERE id = ?");
        $gname->execute([$gid]);
        $groupName = $gname->fetchColumn();

        if (!$groupName) {
            $severity = 'err';
            $msg = '✗ <strong>Grup tidak ditemukan.</strong>';
        } else {
            $res = export_group_to_csv($pdo, $gid, $groupName);
            if (!$res['ok']) {
                $severity = 'err';
                $msg = '✗ <strong>Export gagal:</strong> ' . e($res['msg']);
            } else {
                $severity = 'ok';
                $link = e($res['href']);
                $msg = "✓ <strong>Export sukses!</strong> Grup <strong>" . e($groupName) . "</strong> "
                     . "→ {$res['count']} baris, {$res['bytes']} bytes. "
                     . "File: <a href='{$link}' target='_blank' rel='noopener'>Download CSV</a>";
                if ($res['count'] === 0) {
                    $severity = 'warn';
                    $msg = "⚠ <strong>Export selesai, namun tidak ada anggota</strong> pada grup <strong>" . e($groupName) . "</strong>. "
                         . "File CSV tetap dibuat: <a href='{$link}' target='_blank' rel='noopener'>Download CSV</a>";
                }
            }
        }
    }
}

// Ambil daftar grup + jumlah anggota
$groups = $pdo->query("
    SELECT g.id, g.name, g.created_at, COUNT(gm.contact_id) AS member_count
    FROM groups g
    LEFT JOIN group_members gm ON gm.group_id = g.id
    GROUP BY g.id, g.name, g.created_at
    ORDER BY g.name
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Group List</title>
<style>
body{font-family:Segoe UI,Arial,Helvetica,sans-serif;margin:0;background:#f7f7fb;color:#222}
header{background:#0d6efd;color:#fff;padding:16px}
main{padding:20px;max-width:1100px;margin:0 auto}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left}
.btn{display:inline-block;background:#0d6efd;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none;border:0;cursor:pointer}
.btn.secondary{background:#6b7280}
.btn.warn{background:#f59e0b}
.btn.success{background:#16a34a}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:16px}
.card h3{margin:0;padding:12px 16px;border-bottom:1px solid #e5e7eb}
.card .body{padding:16px}
.badge{display:inline-block;padding:2px 6px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px}
.alert{border-radius:6px;padding:10px 12px;margin:10px 0}
.alert.ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.alert.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.alert.warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
.tools{display:flex;gap:8px;flex-wrap:wrap}
a.link{color:#0d6efd;text-decoration:none}
a.link:hover{text-decoration:underline}
</style>
</head>
<body>
<header>
  <h2>Group List</h2>
  <div class="tools">
    <a class="btn secondary" href="index.php">⟵ Dashboard</a>
    <a class="btn" href="contacts.php#group-builder">➕ Buat Group dari Kontak</a>
  </div>
</header>
<main>

  <?php if ($msg): ?>
    <div class="alert <?= e($severity ?: 'ok') ?>"><?= $msg ?></div>
  <?php endif; ?>

  <div class="card">
    <h3>Daftar Grup</h3>
    <div class="body">
      <?php if (empty($groups)): ?>
        <p class="warn">Belum ada grup. Silakan buat grup di halaman <a class="link" href="contacts.php#group-builder">Kontak → Buat Group</a>.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th style="width:72px">ID</th>
              <th>Nama Grup</th>
              <th style="width:140px">Anggota</th>
              <th style="width:200px">Dibuat</th>
              <th style="width:220px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($groups as $g): ?>
              <tr>
                <td><?= (int)$g['id'] ?></td>
                <td><?= e($g['name']) ?></td>
                <td><span class="badge"><?= (int)$g['member_count'] ?></span></td>
                <td><?= e($g['created_at']) ?></td>
                <td>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Export anggota grup ini ke CSV?')">
                    <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
                    <button class="btn success" name="export_group" value="1">⬇ Export CSV</button>
                  </form>
                  <a class="btn secondary" href="contacts.php#group-builder" title="Tambah/atur anggota via kontak">Kelola Anggota</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

</main>
</body>
</html>