<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';

$pdo = DB::conn();

echo "=== GROUPS ===\n";
$groups = $pdo->query("SELECT id, name FROM `groups` ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
foreach ($groups as $g) {
    echo "[{$g['id']}] {$g['name']}\n";
}

echo "\n=== GROUP_MEMBERS ===\n";
$members = $pdo->query("
    SELECT 
        g.name as group_name,
        c.email,
        c.display_name
    FROM `group_members` gm
    JOIN `groups` g ON g.id = gm.group_id
    JOIN `contacts` c ON c.id = gm.contact_id
    ORDER BY g.name, c.email
")->fetchAll(PDO::FETCH_ASSOC);

$currentGroup = '';
foreach ($members as $m) {
    if ($m['group_name'] !== $currentGroup) {
        echo "\n[{$m['group_name']}]\n";
        $currentGroup = $m['group_name'];
    }
    echo "  {$m['email']} ({$m['display_name']})\n";
}

echo "\n=== TEST: Cari grup untuk email tertentu ===\n";
$testEmails = ['Agoes.Komar@Daihatsu.astra.co.id', 'Aji.Prasetyo@Daihatsu.astra.co.id', 'erni@alifmb.co.id'];
foreach ($testEmails as $email) {
    echo "\nEmail: $email\n";
    $stmt = $pdo->prepare("
        SELECT DISTINCT g.id, g.name
        FROM `group_members` gm
        JOIN `groups` g ON g.id = gm.group_id
        JOIN `contacts` c ON c.id = gm.contact_id
        WHERE c.email = ?
        ORDER BY g.name
    ");
    $stmt->execute([$email]);
    $emailGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($emailGroups)) {
        echo "  → Tidak ada di grup manapun\n";
    } else {
        foreach ($emailGroups as $g) {
            echo "  → [{$g['id']}] {$g['name']}\n";
        }
    }
}
?>
