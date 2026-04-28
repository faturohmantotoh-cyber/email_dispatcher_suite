<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';
ensure_dirs();

// Get user info
$user = $_SESSION['user'] ?? null;
if (!$user) header('Location: login.php');

// Get documentation requested
$doc = $_GET['doc'] ?? 'manual';
$section = $_GET['section'] ?? '';

// Map doc parameters to files
$docs = [
  'manual' => [
    'title' => '📖 Manual Operasional Lengkap',
    'file' => '../MANUAL_OPERASIONAL.md',
    'description' => 'Panduan komprehensif 16 modul untuk seluruh aplikasi'
  ],
  'modul1' => [
    'title' => '👥 Modul 1: Manajemen Kontak',
    'file' => '../MODUL_1_MANAJEMEN_KONTAK.md',
    'description' => 'Panduan lengkap untuk mengelola kontak'
  ],
  'modul2' => [
    'title' => '✉️ Modul 2: Compose & Send Email',
    'file' => '../MODUL_2_COMPOSE_EMAIL.md',
    'description' => 'Panduan lengkap untuk membuat dan mengirim email'
  ],
  'quickstart' => [
    'title' => '🚀 Quick Start Guide',
    'file' => '../QUICK_START_UPLOAD.md',
    'description' => 'Panduan singkat untuk memulai'
  ]
];

// Get the documentation
$current_doc = $docs[$doc] ?? $docs['manual'];
$file_path = __DIR__ . '/' . $current_doc['file'];

// Read markdown file
$markdown_content = '';
if (file_exists($file_path)) {
  $markdown_content = file_get_contents($file_path);
} else {
  $markdown_content = "# File tidak ditemukan\n\nDocumentation file tidak ditemukan: " . $current_doc['file'];
}

// Simple markdown to HTML converter
function markdown_to_html($markdown) {
  $html = htmlspecialchars($markdown);
  
  // Headers
  $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
  $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
  $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $html);
  
  // Bold and Italic
  $html = preg_replace('/\*\*(.*?)\*\*/m', '<strong>$1</strong>', $html);
  $html = preg_replace('/\*(.*?)\*/m', '<em>$1</em>', $html);
  
  // Code blocks
  $html = preg_replace('/```(.*?)```/ms', '<pre><code>$1</code></pre>', $html);
  
  // Inline code
  $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
  
  // Lists
  $html = preg_replace('/^\- (.*?)$/m', '<li>$1</li>', $html);
  $html = preg_replace('/(<li>.*?<\/li>)/ms', '<ul>$1</ul>', $html);
  
  // Paragraphs
  $html = preg_replace('/\n\n/', '</p><p>', $html);
  $html = '<p>' . $html . '</p>';
  
  // Links
  $html = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank">$1</a>', $html);
  
  return $html;
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title><?php echo $current_doc['title']; ?> - Help</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/custom.css?v=3.0">
<style>
  .help-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem;
  }

  .help-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #E3F2FD 0%, #F0F7FF 100%);
    border-radius: 0.75rem;
    border-left: 4px solid #0052CC;
  }

  .help-header h1 {
    margin: 0;
    color: #0052CC;
    font-size: 2rem;
  }

  .help-nav {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
  }

  .help-nav a, .help-nav button {
    padding: 0.6rem 1.2rem;
    border-radius: 0.5rem;
    border: none;
    background: #E3F2FD;
    color: #0052CC;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 600;
  }

  .help-nav a:hover, .help-nav button:hover {
    background: #BBDEFB;
    transform: translateY(-2px);
  }

  .help-nav a.active {
    background: #0052CC;
    color: white;
  }

  .help-content {
    background: white;
    border-radius: 0.75rem;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    line-height: 1.8;
  }

  .help-content h1 {
    color: #0052CC;
    border-bottom: 3px solid #E3F2FD;
    padding-bottom: 1rem;
    margin-top: 2rem;
    margin-bottom: 1rem;
  }

  .help-content h1:first-child {
    margin-top: 0;
  }

  .help-content h2 {
    color: #003fa3;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
  }

  .help-content h3 {
    color: #0052CC;
    margin-top: 1.25rem;
    margin-bottom: 0.75rem;
  }

  .help-content p {
    margin: 0.75rem 0;
    color: #424242;
  }

  .help-content code {
    background: #F5F5F5;
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    color: #D32F2F;
  }

  .help-content pre {
    background: #F5F5F5;
    padding: 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    border-left: 4px solid #0052CC;
    margin: 1rem 0;
  }

  .help-content pre code {
    background: none;
    padding: 0;
    color: #424242;
  }

  .help-content ul {
    margin: 1rem 0;
    padding-left: 2rem;
  }

  .help-content li {
    margin: 0.5rem 0;
    color: #424242;
  }

  .help-content a {
    color: #0052CC;
    text-decoration: none;
    border-bottom: 1px dotted #0052CC;
  }

  .help-content a:hover {
    text-decoration: underline;
    color: #003fa3;
  }

  .help-footer {
    text-align: center;
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid #E3F2FD;
    color: #9E9E9E;
    font-size: 0.9rem;
  }

  .back-button {
    display: inline-block;
    margin-bottom: 2rem;
    padding: 0.6rem 1.2rem;
    background: #E3F2FD;
    color: #0052CC;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
  }

  .back-button:hover {
    background: #BBDEFB;
    transform: translateY(-2px);
  }

  @media (max-width: 768px) {
    .help-container {
      padding: 1rem 0.5rem;
    }

    .help-header {
      flex-direction: column;
      text-align: center;
    }

    .help-header h1 {
      font-size: 1.5rem;
    }

    .help-nav {
      justify-content: center;
    }

    .help-content {
      padding: 1.5rem 1rem;
    }
  }
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main>
  <div class="help-container">
    <a href="index.php" class="back-button">← Back to Dashboard</a>

    <div class="help-header">
      <div>
        <h1><?php echo $current_doc['title']; ?></h1>
        <p style="margin: 0.5rem 0 0 0; color: #666;"><?php echo $current_doc['description']; ?></p>
      </div>
      <div style="text-align: right; min-width: 150px;">
        <a href="<?php echo $current_doc['file']; ?>" download class="btn" style="display: inline-block;">📥 Download MD</a>
      </div>
    </div>

    <div class="help-nav">
      <a href="help.php?doc=manual" <?php echo ($doc === 'manual' ? 'class="active"' : ''); ?>>📖 Manual Lengkap</a>
      <a href="help.php?doc=modul1" <?php echo ($doc === 'modul1' ? 'class="active"' : ''); ?>>👥 Modul 1: Kontak</a>
      <a href="help.php?doc=modul2" <?php echo ($doc === 'modul2' ? 'class="active"' : ''); ?>>✉️ Modul 2: Email</a>
      <a href="help.php?doc=quickstart" <?php echo ($doc === 'quickstart' ? 'class="active"' : ''); ?>>🚀 Quick Start</a>
      <a href="index.php">← Kembali ke Dashboard</a>
    </div>

    <div class="help-content">
      <?php echo $markdown_content; ?>
    </div>

    <div class="help-footer">
      <p>📚 Documentation Center | Last Updated: <?php echo date('d M Y'); ?></p>
    </div>
  </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
