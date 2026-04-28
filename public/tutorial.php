<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';
require_once __DIR__ . '/../lib/security.php';
ensure_dirs();
$pdo = DB::conn();
SecurityManager::init($pdo);

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: login.php');
    exit;
}

$tutorial = $_GET['t'] ?? 'quickstart';

// Tutorial Data
$tutorialData = [
    'quickstart' => [
        'title' => 'Quick Start: Upload Kontak',
        'icon' => '🚀',
        'color' => 'blue',
        'duration' => '5 menit',
        'description' => 'Panduan cepat untuk upload kontak pertama kali',
        'steps' => [
            [
                'id' => 1,
                'title' => 'Buka Menu Kontak',
                'short' => 'Buka Menu',
                'content' => 'contact_step1',
                'hasChecklist' => false
            ],
            [
                'id' => 2,
                'title' => 'Klik Button Upload',
                'short' => 'Klik Upload',
                'content' => 'contact_step2',
                'hasChecklist' => false
            ],
            [
                'id' => 3,
                'title' => 'Pilih Opsi Upload',
                'short' => 'Pilih Opsi',
                'content' => 'contact_step3',
                'hasChecklist' => true,
                'checklist' => ['Download template CSV (jika belum punya)', 'Isi data kontak di Excel/Google Sheets', 'Pilih opsi upload yang sesuai']
            ],
            [
                'id' => 4,
                'title' => 'Upload File CSV',
                'short' => 'Upload File',
                'content' => 'contact_step4',
                'hasChecklist' => true,
                'checklist' => ['Siapkan file CSV dengan format benar', 'Drag & drop atau browse file', 'Tunggu upload selesai']
            ],
            [
                'id' => 5,
                'title' => 'Lihat Hasil Upload',
                'short' => 'Lihat Hasil',
                'content' => 'contact_step5',
                'hasChecklist' => false
            ],
            [
                'id' => 6,
                'title' => 'Selesai! 🎉',
                'short' => 'Selesai',
                'content' => 'contact_step6',
                'hasChecklist' => false
            ]
        ]
    ],
    'contacts' => [
        'title' => 'Manajemen Kontak Lengkap',
        'icon' => '👥',
        'color' => 'green',
        'duration' => '8 menit',
        'description' => 'Panduan lengkap mengelola kontak - CRUD, import, export',
        'steps' => [
            [
                'id' => 1,
                'title' => 'Akses Menu Kontak',
                'short' => 'Akses Menu',
                'content' => 'contacts_step1',
                'hasChecklist' => false
            ],
            [
                'id' => 2,
                'title' => 'Tambah Kontak Manual',
                'short' => 'Tambah Kontak',
                'content' => 'contacts_step2',
                'hasChecklist' => true,
                'checklist' => ['Klik tombol "Tambah Kontak"', 'Isi nama dan email', 'Pilih grup (opsional)', 'Simpan kontak']
            ],
            [
                'id' => 3,
                'title' => 'Import dari CSV',
                'short' => 'Import CSV',
                'content' => 'contacts_step3',
                'hasChecklist' => true,
                'checklist' => ['Siapkan file CSV', 'Klik "Upload Manual Kontak"', 'Pilih file dan upload', 'Verifikasi hasil import']
            ],
            [
                'id' => 4,
                'title' => 'Edit & Hapus Kontak',
                'short' => 'Edit/Hapus',
                'content' => 'contacts_step4',
                'hasChecklist' => false
            ],
            [
                'id' => 5,
                'title' => 'Export Kontak',
                'short' => 'Export',
                'content' => 'contacts_step5',
                'hasChecklist' => false
            ]
        ]
    ],
    'compose' => [
        'title' => 'Kirim Email dengan Lampiran',
        'icon' => '✉️',
        'color' => 'blue',
        'duration' => '10 menit',
        'description' => 'Cara membuat email, pilih penerima, dan kirim dengan attachment',
        'steps' => [
            [
                'id' => 1,
                'title' => 'Buka Halaman Compose',
                'short' => 'Buka Compose',
                'content' => 'compose_step1',
                'hasChecklist' => false
            ],
            [
                'id' => 2,
                'title' => 'Pilih Penerima',
                'short' => 'Pilih Penerima',
                'content' => 'compose_step2',
                'hasChecklist' => true,
                'checklist' => ['Pilih kontak individu', 'Atau pilih grup', 'Verifikasi daftar penerima', 'Tambah CC jika perlu']
            ],
            [
                'id' => 3,
                'title' => 'Tulis Email',
                'short' => 'Tulis Email',
                'content' => 'compose_step3',
                'hasChecklist' => true,
                'checklist' => ['Masukkan subject', 'Tulis body email', 'Gunakan rich text editor', 'Preview hasil']
            ],
            [
                'id' => 4,
                'title' => 'Upload Lampiran',
                'short' => 'Upload Lampiran',
                'content' => 'compose_step4',
                'hasChecklist' => true,
                'checklist' => ['Klik "Browse Files"', 'Pilih file dari komputer', 'Atau drag & drop', 'Verifikasi file terupload']
            ],
            [
                'id' => 5,
                'title' => 'AI Matching',
                'short' => 'AI Matching',
                'content' => 'compose_step5',
                'hasChecklist' => false
            ],
            [
                'id' => 6,
                'title' => 'Preview & Kirim',
                'short' => 'Kirim Email',
                'content' => 'compose_step6',
                'hasChecklist' => true,
                'checklist' => ['Preview matching attachment', 'Cek similarity score', 'Klik "Kirim Email"', 'Tunggu konfirmasi sukses']
            ]
        ]
    ],
    'templates' => [
        'title' => 'Template Email',
        'icon' => '📝',
        'color' => 'purple',
        'duration' => '8 menit',
        'description' => 'Membuat dan menggunakan template email',
        'steps' => [
            [
                'id' => 1,
                'title' => 'Akses Template Manager',
                'short' => 'Akses Template',
                'content' => 'template_step1',
                'hasChecklist' => false
            ],
            [
                'id' => 2,
                'title' => 'Buat Template Baru',
                'short' => 'Buat Template',
                'content' => 'template_step2',
                'hasChecklist' => true,
                'checklist' => ['Klik "Tambah Template"', 'Isi nama template', 'Tulis subject', 'Buat body template']
            ],
            [
                'id' => 3,
                'title' => 'Gunakan Variable',
                'short' => 'Variable',
                'content' => 'template_step3',
                'hasChecklist' => false
            ],
            [
                'id' => 4,
                'title' => 'Simpan & Gunakan',
                'short' => 'Simpan',
                'content' => 'template_step4',
                'hasChecklist' => true,
                'checklist' => ['Simpan template', 'Buka Compose', 'Pilih dari dropdown template', 'Email auto-terisi']
            ]
        ]
    ],
    'ai-matching' => [
        'title' => 'AI Attachment Matching',
        'icon' => '🤖',
        'color' => 'orange',
        'duration' => '5 menit',
        'description' => 'Menggunakan AI untuk matching lampiran dengan penerima',
        'steps' => [
            [
                'id' => 1,
                'title' => 'Pengenalan AI Matching',
                'short' => 'Pengenalan',
                'content' => 'ai_step1',
                'hasChecklist' => false
            ],
            [
                'id' => 2,
                'title' => 'Aktifkan AI Matching',
                'short' => 'Aktifkan AI',
                'content' => 'ai_step2',
                'hasChecklist' => true,
                'checklist' => ['Buka halaman Compose', 'Upload lampiran', 'Ceklist "Gunakan AI Matching"', 'Lihat similarity score']
            ],
            [
                'id' => 3,
                'title' => 'Setup Groq API',
                'short' => 'Setup API',
                'content' => 'ai_step3',
                'hasChecklist' => false
            ],
            [
                'id' => 4,
                'title' => 'Interpretasi Hasil',
                'short' => 'Hasil Matching',
                'content' => 'ai_step4',
                'hasChecklist' => false
            ]
        ]
    ]
];

$current = $tutorialData[$tutorial] ?? $tutorialData['quickstart'];
$steps = json_encode($current['steps']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $current['title'] ?> - Tutorial Interaktif</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --purple: #8b5cf6;
            --orange: #f97316;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: var(--gray-800); }
        .app-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        
        /* Header */
        .tutorial-header { text-align: center; margin-bottom: 2rem; color: white; }
        .tutorial-header h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; }
        .tutorial-header p { font-size: 1.125rem; opacity: 0.9; }
        
        /* Tutorial Nav */
        .tutorial-nav { display: flex; gap: 1rem; justify-content: center; margin-bottom: 2rem; flex-wrap: wrap; }
        .tutorial-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background: rgba(255,255,255,0.15); border: 2px solid rgba(255,255,255,0.3); border-radius: 1rem; color: white; text-decoration: none; font-weight: 600; transition: all 0.3s ease; backdrop-filter: blur(10px); }
        .tutorial-btn:hover { background: rgba(255,255,255,0.25); transform: translateY(-2px); }
        .tutorial-btn.active { background: white; color: var(--gray-800); border-color: white; }
        .tutorial-btn .duration { font-size: 0.75rem; background: rgba(255,255,255,0.3); padding: 0.25rem 0.5rem; border-radius: 1rem; }
        .tutorial-btn.active .duration { background: var(--gray-200); }
        
        /* Main Card */
        .tutorial-card { background: white; border-radius: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; }
        
        /* Progress */
        .progress-section { background: var(--gray-50); padding: 1.5rem 2rem; border-bottom: 1px solid var(--gray-200); }
        .progress-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .progress-title { font-weight: 700; color: var(--gray-800); display: flex; align-items: center; gap: 0.5rem; }
        .progress-percent { font-size: 1.5rem; font-weight: 800; color: var(--primary); }
        .progress-bar-bg { height: 0.75rem; background: var(--gray-200); border-radius: 1rem; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #3b82f6, #8b5cf6); border-radius: 1rem; transition: width 0.5s ease; width: 0%; }
        
        /* Step Nav */
        .step-nav { display: flex; gap: 0.5rem; padding: 1rem 2rem; background: white; border-bottom: 1px solid var(--gray-200); overflow-x: auto; }
        .step-dot { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; cursor: pointer; border-radius: 0.75rem; transition: all 0.3s ease; min-width: 80px; }
        .step-dot:hover { background: var(--gray-100); }
        .step-dot.active { background: #eff6ff; }
        .step-dot.completed { background: #f0fdf4; }
        .step-number { width: 2.5rem; height: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; transition: all 0.3s ease; background: var(--gray-200); color: var(--gray-600); }
        .step-dot.active .step-number { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4); }
        .step-dot.completed .step-number { background: var(--success); color: white; }
        .step-label { font-size: 0.75rem; font-weight: 600; color: var(--gray-600); text-align: center; }
        .step-dot.active .step-label { color: var(--primary); }
        .step-dot.completed .step-label { color: var(--success); }
        
        /* Content */
        .content-area { padding: 2rem; min-height: 400px; }
        .step-content { display: none; animation: fadeIn 0.5s ease; }
        .step-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .step-title { font-size: 1.875rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--gray-900); }
        
        /* Visual Grid & Cards */
        .visual-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin: 1.5rem 0; }
        .visual-card { background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border: 2px solid var(--gray-200); border-radius: 1.5rem; padding: 1.5rem; transition: all 0.3s ease; }
        .visual-card:hover { border-color: var(--primary); transform: translateY(-4px); box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.2); }
        .visual-card.clickable { cursor: pointer; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-color: #3b82f6; }
        .visual-card.clickable:hover { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); }
        .visual-icon { width: 4rem; height: 4rem; background: white; border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .visual-card h3 { font-size: 1.125rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--gray-900); }
        .visual-card p { color: var(--gray-600); line-height: 1.6; font-size: 0.95rem; }
        .action-btn { display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1rem; padding: 0.625rem 1.25rem; background: var(--primary); color: white; border-radius: 0.75rem; text-decoration: none; font-weight: 600; font-size: 0.875rem; transition: all 0.2s ease; }
        .action-btn:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
        /* Action List */
        .action-list { list-style: none; margin: 1.5rem 0; }
        .action-item { display: flex; align-items: flex-start; gap: 1rem; padding: 1.25rem; background: white; border: 2px solid var(--gray-200); border-radius: 1rem; margin-bottom: 1rem; transition: all 0.3s ease; }
        .action-item:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1); }
        .action-icon { width: 3rem; height: 3rem; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        .action-content h4 { font-weight: 700; margin-bottom: 0.25rem; color: var(--gray-900); }
        .action-content p { color: var(--gray-600); font-size: 0.95rem; }
        
        /* Code Preview */
        .code-preview { background: #1f2937; border-radius: 1rem; overflow: hidden; margin: 1.5rem 0; }
        .code-header { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #111827; border-bottom: 1px solid #374151; }
        .code-title { display: flex; align-items: center; gap: 0.5rem; color: #9ca3af; font-size: 0.875rem; font-weight: 500; }
        .code-copy { padding: 0.375rem 0.75rem; background: #374151; color: #e5e7eb; border: none; border-radius: 0.5rem; font-size: 0.75rem; cursor: pointer; transition: all 0.2s ease; }
        .code-copy:hover { background: #4b5563; }
        .code-content { padding: 1.25rem; font-family: 'JetBrains Mono', monospace; font-size: 0.875rem; line-height: 1.7; color: #e5e7eb; overflow-x: auto; }
        
        /* Tip Box */
        .tip-box { display: flex; gap: 1rem; padding: 1.25rem; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #f59e0b; border-radius: 1rem; margin: 1.5rem 0; }
        .tip-box.blue { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-color: #3b82f6; }
        .tip-icon { font-size: 1.5rem; }
        .tip-content h4 { font-weight: 700; color: #92400e; margin-bottom: 0.25rem; }
        .tip-box.blue .tip-content h4 { color: #1e40af; }
        .tip-content p { color: #a16207; font-size: 0.95rem; }
        .tip-box.blue .tip-content p { color: #1e40af; }
        
        /* Success Box */
        .success-box { display: flex; align-items: center; gap: 1rem; padding: 1.5rem; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border: 2px solid var(--success); border-radius: 1rem; margin: 1.5rem 0; }
        .success-icon { width: 3rem; height: 3rem; background: var(--success); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .success-content h4 { font-weight: 700; color: #065f46; }
        .success-content p { color: #047857; }
        
        /* Checklist */
        .checklist-container { margin-top: 2rem; padding: 1.5rem; background: var(--gray-50); border-radius: 1rem; }
        .checklist-title { margin-bottom: 1rem; color: var(--gray-800); font-weight: 700; }
        .checklist-item { display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 0.75rem; background: white; border-radius: 0.75rem; margin-bottom: 0.75rem; transition: all 0.2s; }
        .checklist-item:hover { background: var(--gray-100); }
        .checklist-item.completed { opacity: 0.7; }
        .checklist-item.completed span { text-decoration: line-through; color: var(--success); }
        .checklist-item input { width: 1.5rem; height: 1.5rem; accent-color: var(--success); cursor: pointer; }
        
        /* Nav Buttons */
        .step-nav-buttons { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 2rem; background: var(--gray-50); border-top: 1px solid var(--gray-200); }
        .nav-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.875rem 1.5rem; border-radius: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; border: none; font-size: 1rem; }
        .nav-btn-prev { background: white; color: var(--gray-700); border: 2px solid var(--gray-300); }
        .nav-btn-prev:hover { border-color: var(--primary); color: var(--primary); }
        .nav-btn-next { background: var(--primary); color: white; }
        .nav-btn-next:hover { background: var(--primary-dark); transform: translateX(4px); }
        .nav-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .mark-complete { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.5rem; background: white; border: 2px solid var(--success); color: var(--success); border-radius: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .mark-complete:hover { background: var(--success); color: white; }
        .mark-complete.completed { background: var(--success); color: white; }
        
        /* Footer */
        .tutorial-footer { text-align: center; padding: 2rem; color: white; opacity: 0.8; }
        .tutorial-footer a { color: white; font-weight: 600; }
        
        @media (max-width: 768px) {
            .app-container { padding: 1rem; }
            .tutorial-header h1 { font-size: 1.75rem; }
            .content-area { padding: 1.5rem; }
            .visual-grid { grid-template-columns: 1fr; }
        }

        /* Print Styles */
        @media print {
            body { background: white; }
            .tutorial-header, .tutorial-nav, .step-nav, .step-nav-buttons, .tutorial-footer, .progress-section { display: none !important; }
            .tutorial-card { box-shadow: none; }
            .step-content { display: block !important; page-break-inside: avoid; }
            .step-content:not(:last-child) { page-break-after: always; }
            .content-area { padding: 0; }
        }

        /* Smooth Scroll */
        html { scroll-behavior: smooth; }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="tutorial-header">
            <h1><span><?= $current['icon'] ?></span> <?= $current['title'] ?></h1>
            <p><?= $current['description'] ?> • ⏱️ <?= $current['duration'] ?></p>
        </div>

        <div class="tutorial-nav">
            <?php foreach ($tutorialData as $key => $t): ?>
                <a href="?t=<?= $key ?>" class="tutorial-btn <?= $tutorial === $key ? 'active' : '' ?>">
                    <span><?= $t['icon'] ?></span>
                    <span><?= $t['title'] ?></span>
                    <span class="duration"><?= $t['duration'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="tutorial-card">
            <div class="progress-section">
                <div class="progress-header">
                    <div class="progress-title">📊 Progress Belajar</div>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <button onclick="printTutorial()" style="padding: 0.5rem 1rem; background: white; border: 2px solid var(--gray-300); border-radius: 0.5rem; cursor: pointer; font-weight: 600; color: var(--gray-700); display: flex; align-items: center; gap: 0.5rem;">
                            🖨️ Print
                        </button>
                        <div class="progress-percent" id="progressText">0%</div>
                    </div>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" id="progressBar"></div>
                </div>
            </div>

            <div class="step-nav" id="stepNav"></div>
            <div class="content-area" id="contentArea"></div>

            <div class="step-nav-buttons">
                <button class="nav-btn nav-btn-prev" id="prevBtn" onclick="prevStep()">← Sebelumnya</button>
                <button class="mark-complete" id="completeBtn" onclick="toggleComplete()">
                    <span id="completeIcon">⭕</span>
                    <span id="completeText">Tandai Selesai</span>
                </button>
                <button class="nav-btn nav-btn-next" id="nextBtn" onclick="nextStep()">Selanjutnya →</button>
            </div>
        </div>

        <div class="tutorial-footer">
            <p>💡 Butuh bantuan? <a href="index.php">Kembali ke Dashboard</a> atau gunakan AI Assistant</p>
        </div>
    </div>

    <script>
        // Tutorial data from PHP
        const steps = <?= $steps ?>;
        const tutorialKey = '<?= $tutorial ?>';
        let currentStep = 0;
        let completedSteps = JSON.parse(localStorage.getItem(`tutorial_${tutorialKey}`) || '[]');

        // Content templates
        const contentTemplates = {
            // CONTACT STEPS
            contact_step1: `
                <div class="visual-grid">
                    <div class="visual-card clickable" onclick="window.open('contacts.php', '_blank')">
                        <div class="visual-icon">📁</div>
                        <h3>Menu Kontak</h3>
                        <p>Klik untuk membuka halaman Contacts di tab baru</p>
                        <span class="action-btn">Buka contacts.php →</span>
                    </div>
                </div>
                <div class="tip-box">
                    <div class="tip-icon">💡</div>
                    <div class="tip-content"><h4>Tip Cepat</h4><p>Anda juga bisa akses langsung via: <code>contacts.php</code> dari menu sidebar</p></div>
                </div>`,
            contact_step2: `
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">1️⃣</div><div class="action-content"><h4>Cari Section "Sinkronisasi Outlook"</h4><p>Biasanya berada di bagian atas halaman contacts</p></div></div>
                    <div class="action-item"><div class="action-icon">2️⃣</div><div class="action-content"><h4>Klik Tombol "📤 Upload Manual Kontak"</h4><p>Tombol berwarna biru dengan ikon upload</p></div></div>
                </div>
                <div class="tip-box"><div class="tip-icon">⚠️</div><div class="tip-content"><h4>Perhatian</h4><p>Pastikan Anda memiliki file CSV yang sudah siap</p></div></div>`,
            contact_step3: `
                <p style="margin-bottom: 1rem; color: var(--gray-600);">Dialog akan muncul dengan 2 pilihan:</p>
                <div class="visual-grid">
                    <div class="visual-card" style="border-color: #f59e0b; background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);"><div class="visual-icon">📄</div><h3>Belum Punya Template?</h3><p>Download template CSV kosong, isi data, lalu upload.</p><ul style="margin-top: 0.75rem; padding-left: 1.25rem; color: var(--gray-600); font-size: 0.9rem;"><li>Klik <strong>"⬇️ Download Template"</strong></li><li>Buka di Excel/Google Sheets</li><li>Isi data kontak</li><li>Save dan ulangi Step 2</li></ul></div>
                    <div class="visual-card" style="border-color: #22c55e; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);"><div class="visual-icon">📁</div><h3>Sudah Punya File CSV?</h3><p>Langsung upload file CSV yang sudah disiapkan.</p><ul style="margin-top: 0.75rem; padding-left: 1.25rem; color: var(--gray-600); font-size: 0.9rem;"><li>Klik <strong>"📁 Sudah Ada, Upload File"</strong></li><li>Pilih file CSV</li><li>Klik Open</li></ul></div>
                </div>`,
            contact_step4: `
                <p style="margin-bottom: 1rem; color: var(--gray-600);">Pilih salah satu cara upload:</p>
                <div class="visual-grid">
                    <div class="visual-card"><div class="visual-icon">🖱️</div><h3>Cara 1: Drag & Drop</h3><p>Drag file CSV langsung ke area upload</p></div>
                    <div class="visual-card"><div class="visual-icon">📂</div><h3>Cara 2: Browse File</h3><p>Klik area upload, pilih file, tekan Open</p></div>
                </div>
                <div class="tip-box"><div class="tip-icon">📋</div><div class="tip-content"><h4>Format CSV</h4><p>Minimal: Kolom <code>Email</code> | Recommended: <code>Name</code> dan <code>Email</code> | Max: 10MB</p></div></div>
                <div class="code-preview"><div class="code-header"><div class="code-title">📄 Contoh Format CSV</div><button class="code-copy" onclick="copyCode(this)">Copy</button></div><div class="code-content">Name,Email
John Doe,john@example.com
Jane Smith,jane@example.com
Company ABC,info@company.com</div></div>`,
            contact_step5: `
                <div class="success-box"><div class="success-icon">✅</div><div class="success-content"><h4>Upload Berhasil!</h4><p>Setelah upload, sistem akan menampilkan ringkasan hasil</p></div></div>
                <div class="code-preview"><div class="code-header"><div class="code-title">📊 Contoh Ringkasan Hasil</div></div><div class="code-content">✅ Upload Berhasil!

Diproses:        25 baris
Ditambahkan:     23 kontak ✓
Skip (duplicate): 2 email ⚠️
Skip (kosong):    0 baris
Error:            0 item</div></div>
                <div class="visual-grid">
                    <div class="visual-card" style="border-color: var(--success);"><div class="visual-icon">✓</div><h3>Ditambahkan</h3><p>Kontak berhasil ditambahkan ke database</p></div>
                    <div class="visual-card" style="border-color: #f59e0b;"><div class="visual-icon">⚠</div><h3>Skip</h3><p>Email duplikat atau baris kosong dilewati</p></div>
                </div>`,
            contact_step6: `
                <div class="success-box" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-color: #3b82f6;"><div class="success-icon" style="background: #3b82f6;">🎉</div><div class="success-content"><h4 style="color: #1e40af;">Selamat!</h4><p style="color: #1e40af;">Anda berhasil mengupload kontak. Pilih tindakan selanjutnya:</p></div></div>
                <div class="visual-grid">
                    <div class="visual-card clickable" onclick="window.open('contacts.php', '_blank')"><div class="visual-icon">📋</div><h3>Lihat Kontak</h3><p>Refresh halaman contacts untuk melihat kontak baru</p><span class="action-btn">Buka Contacts →</span></div>
                    <div class="visual-card clickable" onclick="restartTutorial()"><div class="visual-icon">🔄</div><h3>Unggah File Lain</h3><p>Ulangi tutorial untuk upload file CSV lainnya</p><span class="action-btn" style="background: var(--success);">Ulangi Tutorial →</span></div>
                    <div class="visual-card clickable" onclick="window.open('compose.php', '_blank')"><div class="visual-icon">✉️</div><h3>Kirim Email</h3><p>Langsung buat email dengan kontak baru</p><span class="action-btn" style="background: var(--purple);">Compose Email →</span></div>
                </div>`,
            
            // COMPOSE STEPS
            compose_step1: `
                <div class="visual-grid">
                    <div class="visual-card clickable" onclick="window.open('compose.php', '_blank')"><div class="visual-icon">✉️</div><h3>Halaman Compose</h3><p>Buka halaman untuk membuat email baru</p><span class="action-btn">Buka Compose →</span></div>
                </div>
                <div class="tip-box"><div class="tip-icon">💡</div><div class="tip-content"><h4>Alternatif</h4><p>Anda juga bisa klik menu "Compose" di sidebar kiri</p></div></div>`,
            compose_step2: `
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">👤</div><div class="action-content"><h4>Pilih Kontak Individu</h4><p>Centang checkbox di sebelah nama kontak yang ingin dikirimi email</p></div></div>
                    <div class="action-item"><div class="action-icon">👥</div><div class="action-content"><h4>Atau Pilih Grup</h4><p>Klik tab "Grup" lalu pilih grup yang sudah dibuat</p></div></div>
                    <div class="action-item"><div class="action-icon">📧</div><div class="action-content"><h4>Tambah CC (Opsional)</h4><p>Masukkan email CC manual di kolom yang tersedia</p></div></div>
                </div>
                <div class="tip-box"><div class="tip-icon">✨</div><div class="tip-content"><h4>Pro Tips</h4><p>Gunakan search box untuk mencari kontak cepat. Bisa pilih banyak kontak sekaligus!</p></div></div>`,
            compose_step3: `
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">📝</div><div class="action-content"><h4>Masukkan Subject</h4><p>Subject email yang jelas dan deskriptif</p></div></div>
                    <div class="action-item"><div class="action-icon">🖊️</div><div class="action-content"><h4>Tulis Body Email</h4><p>Gunakan rich text editor untuk format teks, warna, link, dll</p></div></div>
                    <div class="action-item"><div class="action-icon">📋</div><div class="action-content"><h4>Gunakan Template (Opsional)</h4><p>Pilih dari dropdown template yang sudah dibuat</p></div></div>
                </div>
                <div class="tip-box blue"><div class="tip-icon">🎯</div><div class="tip-content"><h4>Best Practice</h4><p>Personalize email dengan menyebut nama penerima. Hindari terlalu banyak formatting.</p></div></div>`,
            compose_step4: `
                <div class="visual-grid">
                    <div class="visual-card"><div class="visual-icon">📎</div><h3>Upload Lampiran</h3><p>Klik "Browse Files" atau drag & drop file ke area upload</p></div>
                    <div class="visual-card"><div class="visual-icon">📁</div><h3>Multiple Files</h3><p>Bisa upload beberapa file sekaligus. Maksimal 10MB per file.</p></div>
                </div>
                <div class="code-preview"><div class="code-header"><div class="code-title">📎 Format yang Didukung</div></div><div class="code-content">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, ZIP, RAR

Maksimal: 10MB per file
Multiple upload: ✅ Didukung</div></div>`,
            compose_step5: `
                <div class="success-box"><div class="success-icon">🤖</div><div class="success-content"><h4>AI Matching Aktif!</h4><p>Sistem akan mencocokkan lampiran dengan penerima berdasarkan nama file</p></div></div>
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">🎯</div><div class="action-content"><h4>Similarity Score</h4><p>Lihat persentase kecocokan antara nama file dan penerima (0-100%)</p></div></div>
                    <div class="action-item"><div class="action-icon">⚙️</div><div class="action-content"><h4>Threshold</h4><p>Atur minimum score (default 60%). Di atas threshold = auto-match</p></div></div>
                    <div class="action-item"><div class="action-icon">✅</div><div class="action-content"><h4>Verifikasi Manual</h4><p>Review hasil matching sebelum kirim. Bisa edit manual jika perlu.</p></div></div>
                </div>
                <div class="tip-box"><div class="tip-icon">💡</div><div class="tip-content"><h4>Tip</h4><p>Nama file yang jelas seperti "John_Doe_Resume.pdf" akan lebih akurat di-match oleh AI</p></div></div>`,
            compose_step6: `
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">👁️</div><div class="action-content"><h4>Preview Matching</h4><p>Review daftar penerima dan lampiran yang matched</p></div></div>
                    <div class="action-item"><div class="action-icon">📊</div><div class="action-content"><h4>Cek Similarity Score</h4><p>Pastikan score tinggi untuk matching yang akurat</p></div></div>
                    <div class="action-item"><div class="action-icon">🚀</div><div class="action-content"><h4>Klik "Kirim Email"</h4><p>Tekan tombol kirim untuk mengirim ke semua penerima</p></div></div>
                </div>
                <div class="success-box"><div class="success-icon">🎉</div><div class="success-content"><h4>Email Terkirim!</h4><p>Anda akan melihat konfirmasi sukses. Email sedang diproses.</p></div></div>
                <div class="visual-grid">
                    <div class="visual-card clickable" onclick="window.open('compose.php', '_blank')"><div class="visual-icon">✉️</div><h3>Kirim Email Lain</h3><p>Buat email baru</p><span class="action-btn">Compose →</span></div>
                    <div class="visual-card clickable" onclick="restartTutorial()"><div class="visual-icon">🔄</div><h3>Ulangi Tutorial</h3><p>Pelajari lagi dari awal</p><span class="action-btn" style="background: var(--success);">Restart →</span></div>
                </div>`,

            // CONTACTS MANAGEMENT STEPS
            contacts_step1: `
                <div class="visual-grid">
                    <div class="visual-card clickable" onclick="window.open('contacts.php', '_blank')"><div class="visual-icon">👥</div><h3>Manajemen Kontak</h3><p>Kelola semua kontak Anda</p><span class="action-btn">Buka Contacts →</span></div>
                </div>`,
            contacts_step2: `
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">➕</div><div class="action-content"><h4>Klik "Tambah Kontak"</h4><p>Tombol hijau di pojok kanan atas</p></div></div>
                    <div class="action-item"><div class="action-icon">📝</div><div class="action-content"><h4>Isi Nama dan Email</h4><p>Masukkan data lengkap kontak</p></div></div>
                    <div class="action-item"><div class="action-icon">🏷️</div><div class="action-content"><h4>Pilih Grup (Opsional)</h4><p>Atau buat grup baru</p></div></div>
                    <div class="action-item"><div class="action-icon">💾</div><div class="action-content"><h4>Simpan Kontak</h4><p>Klik tombol simpan</p></div></div>
                </div>`,
            contacts_step3: `
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">📄</div><div class="action-content"><h4>Siapkan File CSV</h4><p>Format: Name,Email atau download template</p></div></div>
                    <div class="action-item"><div class="action-icon">📤</div><div class="action-content"><h4>Klik "Upload Manual Kontak"</h4><p>Di section Sinkronisasi Outlook</p></div></div>
                    <div class="action-item"><div class="action-icon">⬆️</div><div class="action-content"><h4>Pilih File dan Upload</h4><p>Drag & drop atau browse file</p></div></div>
                    <div class="action-item"><div class="action-icon">✅</div><div class="action-content"><h4>Verifikasi Hasil</h4><p>Cek ringkasan: added, skip, error</p></div></div>
                </div>`,
            contacts_step4: `
                <div class="visual-grid">
                    <div class="visual-card"><div class="visual-icon">✏️</div><h3>Edit Kontak</h3><p>Klik icon edit untuk mengubah data kontak</p></div>
                    <div class="visual-card"><div class="visual-icon">🗑️</div><h3>Hapus Kontak</h3><p>Klik icon trash untuk menghapus. Ada konfirmasi.</p></div>
                    <div class="visual-card"><div class="visual-icon">🔍</div><h3>Search & Filter</h3><p>Gunakan search box untuk mencari kontak cepat</p></div>
                </div>`,
            contacts_step5: `
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">📊</div><div class="action-content"><h4>Pilih Format Export</h4><p>CSV atau Excel</p></div></div>
                    <div class="action-item"><div class="action-icon">⬇️</div><div class="action-content"><h4>Klik "Export"</h4><p>File akan didownload otomatis</p></div></div>
                </div>
                <div class="success-box"><div class="success-icon">✅</div><div class="success-content"><h4>Export Berhasil!</h4><p>File siap digunakan</p></div></div>`,

            // TEMPLATE STEPS
            template_step1: `
                <div class="visual-grid">
                    <div class="visual-card clickable" onclick="window.open('templates.php', '_blank')"><div class="visual-icon">📝</div><h3>Template Manager</h3><p>Kelola template email Anda</p><span class="action-btn">Buka Templates →</span></div>
                </div>`,
            template_step2: `
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">➕</div><div class="action-content"><h4>Klik "Tambah Template"</h4><p>Buat template baru</p></div></div>
                    <div class="action-item"><div class="action-icon">🏷️</div><div class="action-content"><h4>Isi Nama Template</h4><p>Nama yang deskriptif, contoh: "Promo Bulanan"</p></div></div>
                    <div class="action-item"><div class="action-icon">📝</div><div class="action-content"><h4>Tulis Subject</h4><p>Subject email yang akan digunakan</p></div></div>
                    <div class="action-item"><div class="action-icon">📄</div><div class="action-content"><h4>Buat Body Template</h4><p>Gunakan rich text editor</p></div></div>
                </div>`,
            template_step3: `
                <div class="tip-box blue"><div class="tip-icon">🔧</div><div class="tip-content"><h4>Variable yang Didukung</h4><p>Gunakan variable untuk personalize email:</p></div></div>
                <div class="code-preview"><div class="code-header"><div class="code-title">📋 Daftar Variable</div></div><div class="code-content">{{name}}         → Nama kontak
{{email}}        → Email kontak
{{group}}        → Nama grup
{{date}}         → Tanggal saat ini
{{company}}      → Nama perusahaan (jika ada)</div></div>
                <div class="tip-box"><div class="tip-icon">💡</div><div class="tip-content"><h4>Contoh Penggunaan</h4><p>Halo {{name}}, selamat datang di {{company}}!</p></div></div>`,
            template_step4: `
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">💾</div><div class="action-content"><h4>Simpan Template</h4><p>Klik tombol simpan</p></div></div>
                    <div class="action-item"><div class="action-icon">✉️</div><div class="action-content"><h4>Buka Compose</h4><p>Pergi ke halaman kirim email</p></div></div>
                    <div class="action-item"><div class="action-icon">📋</div><div class="action-content"><h4>Pilih dari Dropdown</h4><p>Pilih template yang sudah dibuat</h4></div></div>
                    <div class="action-item"><div class="action-icon">✨</div><div class="action-content"><h4>Auto-terisi!</h4><p>Subject dan body otomatis terisi</p></div></div>
                </div>
                <div class="success-box"><div class="success-icon">🎉</div><div class="success-content"><h4>Template Siap Digunakan!</h4><p>Hemat waktu dengan template email</p></div></div>`,

            // AI STEPS
            ai_step1: `
                <div class="success-box" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-color: #f59e0b;"><div class="success-icon" style="background: #f59e0b;">🤖</div><div class="success-content"><h4 style="color: #92400e;">Apa itu AI Matching?</h4><p style="color: #a16207;">AI akan mencocokkan lampiran dengan penerima berdasarkan nama file dan email</p></div></div>
                <div class="visual-grid">
                    <div class="visual-card"><div class="visual-icon">🎯</div><h3>Smart Matching</h3><p>Menggunakan Groq AI untuk analisis similarity</p></div>
                    <div class="visual-card"><div class="visual-icon">⚡</div><h3>Cepat & Akurat</h3><p>Proses matching dalam hitungan detik</p></div>
                </div>`,
            ai_step2: `
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">✉️</div><div class="action-content"><h4>Buka Halaman Compose</h4><p>Siapkan email baru</p></div></div>
                    <div class="action-item"><div class="action-icon">📎</div><div class="action-content"><h4>Upload Lampiran</h4><p>Tambahkan file yang akan dikirim</p></div></div>
                    <div class="action-item"><div class="action-icon">☑️</div><div class="action-content"><h4>Ceklist "Gunakan AI Matching"</h4><p>Aktifkan fitur AI</p></div></div>
                    <div class="action-item"><div class="action-icon">📊</div><div class="action-content"><h4>Lihat Similarity Score</h4><p>AI menampilkan persentase kecocokan</p></div></div>
                </div>`,
            ai_step3: `
                <div class="tip-box"><div class="tip-icon">🔑</div><div class="tip-content"><h4>Setup Groq API Key</h4><p>Tambahkan GROQ_API_KEY di file config.php</p></div></div>
                <div class="code-preview"><div class="code-header"><div class="code-title">⚙️ config.php</div><button class="code-copy" onclick="copyCode(this)">Copy</button></div><div class="code-content">define('GROQ_API_KEY', 'your-api-key-here');
define('GROQ_MODEL', 'llama3-8b-8192');</div></div>
                <div class="tip-box blue"><div class="tip-icon">💡</div><div class="tip-content"><h4>Dapatkan API Key</h4><p>Daftar gratis di groq.com untuk mendapatkan API key</p></div></div>`,
            ai_step4: `
                <div class="action-list">
                    <div class="action-item"><div class="action-icon">🟢</div><div class="action-content"><h4>Score > 80% (Hijau)</h4><p>Matching sangat akurat, bisa langsung kirim</p></div></div>
                    <div class="action-item"><div class="action-icon">🟡</div><div class="action-content"><h4>Score 60-80% (Kuning)</h4><p>Cocok, tapi perlu verifikasi manual</p></div></div>
                    <div class="action-item"><div class="action-icon">🔴</div><div class="action-content"><h4>Score < 60% (Merah)</h4><p>Tidak cocok, perlu edit manual</p></div></div>
                </div>
                <div class="success-box"><div class="success-icon">✅</div><div class="success-content"><h4>Matching Selesai!</h4><p>Review hasil dan kirim email dengan confidence</p></div></div>`
        };

        // Initialize
        function init() {
            renderStepNav();
            renderSteps();
            updateUI();
        }

        function renderStepNav() {
            const nav = document.getElementById('stepNav');
            nav.innerHTML = steps.map((step, index) => `
                <div class="step-dot ${index === currentStep ? 'active' : ''} ${completedSteps.includes(index) ? 'completed' : ''}" onclick="goToStep(${index})">
                    <div class="step-number">${completedSteps.includes(index) ? '✓' : index + 1}</div>
                    <div class="step-label">${step.short}</div>
                </div>
            `).join('');
        }

        function renderSteps() {
            const content = document.getElementById('contentArea');
            content.innerHTML = steps.map((step, index) => `
                <div class="step-content ${index === currentStep ? 'active' : ''}" data-step="${index}">
                    <h2 class="step-title">${step.id}. ${step.title}</h2>
                    ${contentTemplates[step.content] || '<p>Content coming soon...</p>'}
                    ${step.hasChecklist ? renderChecklist(index, step.checklist) : ''}
                </div>
            `).join('');
        }

        function renderChecklist(stepIndex, items) {
            const storageKey = `checklist_${tutorialKey}_${stepIndex}`;
            const checked = JSON.parse(localStorage.getItem(storageKey) || '[]');
            
            return `
                <div class="checklist-container">
                    <div class="checklist-title">✅ Checklist Langkah ${stepIndex + 1}</div>
                    ${items.map((item, i) => `
                        <label class="checklist-item ${checked.includes(i) ? 'completed' : ''}">
                            <input type="checkbox" ${checked.includes(i) ? 'checked' : ''} onchange="toggleChecklist(${stepIndex}, ${i})">
                            <span>${item}</span>
                        </label>
                    `).join('')}
                </div>
            `;
        }

        function toggleChecklist(stepIndex, itemIndex) {
            const storageKey = `checklist_${tutorialKey}_${stepIndex}`;
            const checked = JSON.parse(localStorage.getItem(storageKey) || '[]');
            
            if (checked.includes(itemIndex)) {
                checked.splice(checked.indexOf(itemIndex), 1);
            } else {
                checked.push(itemIndex);
            }
            
            localStorage.setItem(storageKey, JSON.stringify(checked));
            
            // Auto-complete step if all checked
            if (checked.length === steps[stepIndex].checklist.length) {
                if (!completedSteps.includes(stepIndex)) {
                    completedSteps.push(stepIndex);
                    localStorage.setItem(`tutorial_${tutorialKey}`, JSON.stringify(completedSteps));
                }
            }
            
            renderStepNav();
            updateProgress();
            renderSteps();
        }

        function nextStep() {
            if (currentStep < steps.length - 1) {
                currentStep++;
                updateUI();
            }
        }

        function prevStep() {
            if (currentStep > 0) {
                currentStep--;
                updateUI();
            }
        }

        function goToStep(index) {
            currentStep = index;
            updateUI();
        }

        function updateUI() {
            renderStepNav();
            document.querySelectorAll('.step-content').forEach((el, idx) => {
                el.classList.toggle('active', idx === currentStep);
            });
            
            document.getElementById('prevBtn').disabled = currentStep === 0;
            document.getElementById('nextBtn').disabled = currentStep === steps.length - 1;
            document.getElementById('nextBtn').textContent = currentStep === steps.length - 1 ? 'Selesai ✅' : 'Selanjutnya →';
            
            const isComplete = completedSteps.includes(currentStep);
            document.getElementById('completeBtn').classList.toggle('completed', isComplete);
            document.getElementById('completeIcon').textContent = isComplete ? '✅' : '⭕';
            document.getElementById('completeText').textContent = isComplete ? 'Selesai!' : 'Tandai Selesai';
            
            updateProgress();
        }

        function toggleComplete() {
            if (completedSteps.includes(currentStep)) {
                completedSteps.splice(completedSteps.indexOf(currentStep), 1);
            } else {
                completedSteps.push(currentStep);
            }
            localStorage.setItem(`tutorial_${tutorialKey}`, JSON.stringify(completedSteps));
            updateUI();
        }

        function updateProgress() {
            const percent = Math.round((completedSteps.length / steps.length) * 100);
            document.getElementById('progressBar').style.width = percent + '%';
            document.getElementById('progressText').textContent = percent + '%';
        }

        function copyCode(btn) {
            const code = btn.closest('.code-preview').querySelector('.code-content').textContent;
            navigator.clipboard.writeText(code).then(() => {
                btn.textContent = 'Copied!';
                setTimeout(() => btn.textContent = 'Copy', 2000);
            });
        }

        function restartTutorial() {
            if (confirm('Reset tutorial ini? Progress akan direset.')) {
                localStorage.removeItem(`tutorial_${tutorialKey}`);
                for (let i = 0; i < steps.length; i++) {
                    localStorage.removeItem(`checklist_${tutorialKey}_${i}`);
                }
                completedSteps = [];
                currentStep = 0;
                updateUI();
            }
        }

        // Keyboard Navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === ' ') {
                e.preventDefault();
                nextStep();
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                prevStep();
            } else if (e.key === 'Home') {
                e.preventDefault();
                goToStep(0);
            } else if (e.key === 'End') {
                e.preventDefault();
                goToStep(steps.length - 1);
            }
        });

        // Confetti Effect when all completed
        function checkAllCompleted() {
            if (completedSteps.length === steps.length) {
                showConfetti();
            }
        }

        function showConfetti() {
            const colors = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
            for (let i = 0; i < 100; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.style.cssText = `
                        position: fixed;
                        width: 12px;
                        height: 12px;
                        background: ${colors[Math.floor(Math.random() * colors.length)]};
                        left: ${Math.random() * 100}vw;
                        top: -20px;
                        border-radius: 50%;
                        z-index: 9999;
                        animation: confetti-fall 3s ease-out forwards;
                    `;
                    document.body.appendChild(confetti);
                    setTimeout(() => confetti.remove(), 3000);
                }, i * 20);
            }
        }

        // Add confetti animation keyframes
        const confettiStyle = document.createElement('style');
        confettiStyle.textContent = `
            @keyframes confetti-fall {
                0% { transform: translateY(0) rotate(0deg); opacity: 1; }
                100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
            }
        `;
        document.head.appendChild(confettiStyle);

        // Override toggleComplete to check for confetti
        const originalToggleComplete = toggleComplete;
        toggleComplete = function() {
            originalToggleComplete();
            checkAllCompleted();
        };

        // Print to PDF function
        function printTutorial() {
            window.print();
        }

        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
