<?php
// public/login.php - Login page dengan keamanan berlapis
session_start();
require_once __DIR__ . '/../config_db.php';
require_once __DIR__ . '/../lib/security.php';

// Redirect jika sudah login
if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Initialize SecurityManager untuk headers
require_once __DIR__ . '/../lib/db.php';
$pdo = DB::conn();
SecurityManager::init($pdo);

// Generate CSRF token
$csrf = SecurityManager::generateCSRFToken();

// Friendly flash message
$flash = '';
$flashType = 'info';
if (isset($_SESSION['flash'])) {
  $flash = $_SESSION['flash'];
  $flashType = isset($_GET['timeout']) ? 'warning' : 'error';
  unset($_SESSION['flash']);
}
?>
<!doctype html>
<html lang="id" data-color-scheme="auto">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light dark">
  <title>Login — Email Dispatcher Suite</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="preload" href="../assets/css/login.css" as="style">
  <link rel="stylesheet" href="../assets/css/login.css">
  <link rel="icon" href="../assets/img/logo.svg" type="image/svg+xml">
  <meta name="theme-color" content="#111827">
</head>
<body>
  <!-- Background animasi garis minimalis -->
  <canvas id="bg-canvas" aria-hidden="true"></canvas>

  <!-- Animasi pesawat email -->
  <div class="plane-container" aria-hidden="true">
    <svg class="plane-icon" viewBox="0 0 24 24" width="32" height="32" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M1 8v9c0 1 .5 1.5 1.5 1.5h18L24 16l-3.5-3H2.5C1.5 13 1 12.5 1 11.5V8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </div>

  <main class="container" role="main">
    <section class="card" aria-labelledby="title">
      <header class="card__header">
        <img src="../assets/img/logo.svg" alt="Logo" class="logo" width="40" height="40">
        <h1 id="title" class="title">1000 EMAIL </h1>
        <p class="subtitle">Silakan masuk untuk melanjutkan</p>
      </header>

      <?php if (!empty($flash)): ?>
      <div class="alert" role="alert">
        <?php echo htmlspecialchars($flash); ?>
      </div>
      <?php endif; ?>

      <form class="form" action="auth_login.php" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

        <div class="field">
          <label for="username">Email / Username</label>
          <div class="input-wrap">
            <input
              id="username"
              name="username"
              type="text"
              inputmode="email"
              autocomplete="username"
              placeholder="nama@perusahaan.com"
              required
              maxlength="100"
              aria-describedby="userHelp">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z"/></svg>
          </div>
          <small id="userHelp" class="help">Gunakan akun perusahaan Anda.</small>
        </div>

        <div class="field">
          <label for="password">Kata Sandi</label>
          <div class="input-wrap">
            <input
              id="password"
              name="password"
              type="password"
              autocomplete="current-password"
              placeholder="••••••••"
              required
              minlength="6"
              aria-describedby="passHint passCaps">
            <button class="btn-icon" type="button" id="togglePass" aria-label="Tampilkan kata sandi">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path id="eyePath" d="M12 5c-7 0-11 7-11 7s4 7 11 7 11-7 11-7-4-7-11-7zm0 12a5 5 0 110-10 5 5 0 010 10z"/></svg>
            </button>
          </div>
          <div class="hints">
            <small id="passCaps" class="hint hint--warn" hidden>Caps Lock aktif</small>
            <small id="passHint" class="hint">Minimal 6 karakter</small>
          </div>
        </div>

        <div class="row">
          <label class="checkbox">
            <input type="checkbox" name="remember" value="1">
            <span>Ingat saya</span>
          </label>

          <a class="link" href="#" id="forgotLink">Lupa kata sandi?</a>
        </div>

        <button class="btn btn--primary" type="submit">Masuk</button>

        <footer class="form__footer">
          <small>© <?php echo date('Y'); ?> PCD Automation — v1.0</small>
          <button class="btn btn--ghost" type="button" id="themeToggle" aria-label="Toggle tema">Tema</button>
        </footer>
      </form>
    </section>
  </main>

  <dialog id="resetDialog" class="dialog">
    <form method="dialog" class="dialog__card">
      <h2>Reset Kata Sandi</h2>
      <p>Hubungi Kang Totoh - Kang Malik sambil bawa Americano !.</p>
      <div class="dialog__actions">
        <button class="btn" value="cancel">Tutup</button>
      </div>
    </form>
  </dialog>

  <script defer src="../assets/js/login.js"></script>
</body>
</html>