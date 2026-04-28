<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';
ensure_dirs();

// Get user info
$user = $_SESSION['user'] ?? null;
if (!$user) header('Location: login.php');

// Get database stats
$pdo = DB::conn();
try {
  $contacts_count = $pdo->query("SELECT COUNT(*) FROM `contacts`")->fetchColumn();
  $groups_count = $pdo->query("SELECT COUNT(*) FROM `groups`")->fetchColumn();
  $attachments_count = $pdo->query("SELECT COUNT(*) FROM `attachments`")->fetchColumn();
  $users_count = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
} catch (Exception $e) {
  $contacts_count = $groups_count = $attachments_count = $users_count = 0;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Dashboard - APLIKASI 1000 EMAIL 2026</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/custom.css?v=3.0">
<style>
  /* Dashboard Grid Layout */
  .dashboard-wrapper {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 0;
    min-height: 100vh;
  }

  /* Sidebar Navigation - Blue Theme */
  .sidebar {
    background: linear-gradient(180deg, #0052CC 0%, #003fa3 100%);
    color: white;
    padding: 2rem 0;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    box-shadow: 2px 0 8px rgba(0, 82, 204, 0.15);
  }

  .sidebar-logo {
    padding: 0 1.5rem 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 1.5rem;
  }

  .sidebar-logo h3 {
    margin: 0;
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
  }

  .sidebar-nav {
    list-style: none;
    margin: 0;
    padding: 0;
  }

  .sidebar-nav li {
    margin: 0;
  }

  .sidebar-nav a {
    display: flex;
    align-items: center;
    gap: 1rem;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    padding: 1rem 1.5rem;
    transition: var(--transition-fast);
    font-weight: 500;
    border-left: 3px solid transparent;
  }

  .sidebar-nav a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: white;
  }

  .sidebar-nav a.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-left-color: white;
  }

  .sidebar-nav svg {
    width: 20px;
    height: 20px;
    fill: currentColor;
  }

  /* Main Content */
  .main-content {
    display: flex;
    flex-direction: column;
  }

  .dashboard-header {
    background: white;
    border-bottom: 1px solid var(--gray-light);
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .dashboard-header h1 {
    margin: 0;
    font-size: 1.75rem;
    color: #0052CC;
    font-weight: 700;
  }

  .user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0052CC, #003fa3);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 2px solid transparent;
    overflow: hidden;
  }

  .user-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0, 82, 204, 0.3);
  }

  .user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .user-details {
    text-align: right;
  }

  .user-details p {
    margin: 0;
    font-size: 0.9rem;
  }

  .user-details .name {
    font-weight: 700;
    color: #0052CC;
  }

  .user-details .role {
    font-size: 0.8rem;
    color: #616161;
    font-weight: 600;
  }

  .dashboard-content {
    padding: 2rem;
    flex: 1;
    position: relative;
    background: transparent;
    overflow: hidden;
  }

  /* Rain & Paper Plane Canvas */
  #dashboardCanvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    pointer-events: none;
  }

  .dashboard-content > *:not(#dashboardCanvas) {
    position: relative;
    z-index: 1;
  }

  .wave-decoration {
    max-width: 100%;
    margin-bottom: -1px;
  }

  /* Stats Grid */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
  }

  .stat-card {
    background: white;
    border-radius: 0.875rem;
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(0, 82, 204, 0.15);
    transition: var(--transition);
    display: flex;
    align-items: flex-start;
    gap: 1.25rem;
  }

  .stat-card:hover {
    box-shadow: var(--shadow-md);
    border-color: #0052CC;
    transform: translateY(-2px);
  }

  .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
  }

  .stat-icon.primary { background: linear-gradient(135deg, var(--primary-soft), rgba(91, 123, 255, 0.15)); color: var(--primary); }
  .stat-icon.success { background: linear-gradient(135deg, var(--success-light), rgba(22, 163, 74, 0.15)); color: var(--success); }
  .stat-icon.warning { background: linear-gradient(135deg, var(--warning-light), rgba(255, 184, 77, 0.15)); color: var(--warning); }
  .stat-icon.danger { background: linear-gradient(135deg, var(--danger-light), rgba(255, 107, 107, 0.15)); color: var(--danger); }

  .stat-content h3 {
    margin: 0 0 0.5rem 0;
    font-size: 0.95rem;
    color: #0052CC;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .stat-content p {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: #0052CC;
  }

  .stat-content .small {
    font-size: 0.8rem;
    color: #616161;
    margin-top: 0.25rem;
    font-weight: 600;
  }

  .info-section {
    background: white;
    border-radius: 0.875rem;
    padding: 2rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(0, 82, 204, 0.15);
  }

  .info-section h2 {
    margin-top: 0;
    border-bottom: 2px solid #E3F2FD;
    padding-bottom: 1rem;
    color: #0052CC;
  }

  .info-section h3 {
    margin: 0 0 1.5rem 0;
    color: #0052CC;
  }

  .info-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
  }

  .info-item {
    padding: 1rem;
    border-left: 3px solid #0052CC;
  }

  .info-item strong {
    color: #0052CC;
  }

  .info-item code {
    background: #F5F8FF;
    padding: 0.4rem 0.8rem;
    border-radius: 0.4rem;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    color: #0052CC;
  }

  /* Quick Action Buttons */
  .quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
  }

  .quick-actions .btn {
    text-align: center;
    justify-content: center;
    width: 100%;
  }

  /* Page Transition Animations */
  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @keyframes fadeOut {
    from {
      opacity: 1;
      transform: translateY(0);
    }
    to {
      opacity: 0;
      transform: translateY(-20px);
    }
  }

  @keyframes slideInFromLeft {
    from {
      opacity: 0;
      transform: translateX(-30px);
    }
    to {
      opacity: 1;
      transform: translateX(0);
    }
  }

  @keyframes slideOut {
    from {
      opacity: 1;
      transform: translateX(0);
    }
    to {
      opacity: 0;
      transform: translateX(30px);
    }
  }

  @keyframes overlayIn {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }

  @keyframes scaleIn {
    from {
      opacity: 0;
      transform: scale(0.95);
    }
    to {
      opacity: 1;
      transform: scale(1);
    }
  }

  /* Page Transition Overlay */
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

  .page-transition::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 30% 50%, rgba(255,255,255,0.1) 0%, transparent 50%);
    pointer-events: none;
  }

  /* Page Content Animation */
  .main-content {
    animation: fadeInUp 0.6s ease-out 0.1s both;
  }

  .main-content.transitioning {
    animation: fadeOut 0.3s ease-out forwards;
  }

  .dashboard-content {
    animation: slideInFromLeft 0.5s ease-out 0.15s both;
  }

  .dashboard-content.transitioning {
    animation: slideOut 0.3s ease-out forwards;
  }

  .stat-card {
    animation: scaleIn 0.4s ease-out forwards;
  }

  .stat-card:nth-child(1) { animation-delay: 0.2s; }
  .stat-card:nth-child(2) { animation-delay: 0.25s; }
  .stat-card:nth-child(3) { animation-delay: 0.3s; }
  .stat-card:nth-child(4) { animation-delay: 0.35s; }

  /* Responsive */
  @media (max-width: 1024px) {
    .dashboard-wrapper {
      grid-template-columns: 200px 1fr;
    }
    
    .sidebar { padding: 1.5rem 0; }
    .sidebar-nav a { padding: 0.8rem 1rem; }
    .sidebar-logo { padding: 0 1rem 1.5rem; }
  }

  @media (max-width: 768px) {
    .dashboard-wrapper {
      grid-template-columns: 1fr;
    }

    .sidebar {
      position: fixed;
      left: -260px;
      height: 100vh;
      width: 260px;
      z-index: 998;
      transition: left 0.3s ease;
    }

    .sidebar.open {
      left: 0;
    }

    .dashboard-header {
      padding: 1rem;
      flex-wrap: wrap;
    }

    .dashboard-header h1 {
      font-size: 1.35rem;
    }

    .user-info {
      gap: 0.75rem;
    }

    .stats-grid {
      grid-template-columns: 1fr;
    }

    .dashboard-content {
      padding: 1.5rem 1rem;
    }
  }

  /* Avatar Modal */
  .avatar-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
  }

  .avatar-modal.active {
    display: flex;
  }

  .avatar-modal-content {
    background: white;
    border-radius: 1rem;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    max-height: 90vh;
    overflow-y: auto;
  }

  .avatar-modal-header {
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .avatar-modal-header h2 {
    margin: 0;
    color: #0052CC;
    font-size: 1.25rem;
  }

  .avatar-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #616161;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .avatar-modal-close:hover {
    color: #0052CC;
  }

  .avatar-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .avatar-option {
    width: 100%;
    aspect-ratio: 1;
    border: 2px solid #E3F2FD;
    border-radius: 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: white;
    transition: all 0.2s ease;
    overflow: hidden;
  }

  .avatar-option:hover {
    border-color: #0052CC;
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 82, 204, 0.2);
  }

  .avatar-option.selected {
    border-color: #0052CC;
    background: rgba(0, 82, 204, 0.1);
    box-shadow: 0 0 0 3px rgba(0, 82, 204, 0.2);
  }

  .avatar-option img,
  .avatar-option-text {
    width: 80%;
    height: 80%;
    object-fit: contain;
  }

  .avatar-option-text {
    font-size: 2rem;
    font-weight: 700;
    color: #0052CC;
  }

  .avatar-upload-section {
    border-top: 1px solid #E3F2FD;
    padding-top: 1.5rem;
    margin-bottom: 1.5rem;
  }

  .avatar-upload-section h3 {
    margin-top: 0;
    color: #0052CC;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .avatar-upload-input {
    display: none;
  }

  .avatar-upload-btn {
    display: block;
    width: 100%;
    padding: 0.75rem 1rem;
    background: #E3F2FD;
    border: 2px dashed #0052CC;
    border-radius: 0.5rem;
    color: #0052CC;
    cursor: pointer;
    text-align: center;
    font-weight: 600;
    transition: all 0.2s ease;
  }

  .avatar-upload-btn:hover {
    background: rgba(0, 82, 204, 0.15);
    border-color: #003fa3;
  }

  .avatar-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
  }

  .avatar-actions button {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s ease;
  }

  .avatar-actions .btn-cancel {
    background: #F3F4F6;
    color: #616161;
  }

  .avatar-actions .btn-cancel:hover {
    background: #E5E7EB;
  }

  .avatar-actions .btn-save {
    background: #0052CC;
    color: white;
  }

  .avatar-actions .btn-save:hover {
    background: #003fa3;
  }

  .avatar-actions .btn-save:disabled {
    background: #CBD5E1;
    cursor: not-allowed;
  }
</style>
</head>
<body>

<div class="dashboard-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <h3>📧 Email Suite</h3>
    </div>
    <nav>
      <ul class="sidebar-nav">
        <li><a href="index.php" class="active">
          <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
          Dashboard
        </a></li>
        <li><a href="contacts.php">
          <svg viewBox="0 0 24 24"><path d="M15 13c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm9 0c-.29 0-.62.02-.97.05 1.16.64 1.7 1.97 1.7 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
          Kontak
        </a></li>
        <li><a href="compose.php">
          <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z"/><path d="M20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
          Compose &amp; Upload
        </a></li>
        <li><a href="templates.php">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M12 7v6m-3-3h6"/></svg>
          📧 Email Templates
        </a></li>
        <li><a href="logs.php">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M16 18H8v2h8v-2zm0-4H8v2h8v-2z"/></svg>
          Log &amp; Rekap
        </a></li>
        <li><a href="settings.php">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 1a11 11 0 0 0-8.8 4.3H1v2h2.2a11 11 0 0 0 0 5.4H1v2h2.2A11 11 0 0 0 12 23a11 11 0 0 0 8.8-4.3h2.2v-2h-2.2a11 11 0 0 0 0-5.4h2.2v-2h-2.2A11 11 0 0 0 12 1zm0 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z"/></svg>
          Pengaturan
        </a></li>
        <li style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 2rem;"><a href="logout.php">
          <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
          Logout
        </a></li>
      </ul>
    </nav>
  </aside>

  <!-- Main Content -->
  <div class="main-content">
    <header class="dashboard-header">
      <h1>Dashboard</h1>
      <div class="user-info">
        <div class="user-avatar" id="userAvatarBtn" style="cursor: pointer;" title="Klik untuk ubah avatar">
          <?php 
            $avatar = $user['avatar'] ?? null;
            $avatarFound = false;
            
            if ($avatar) {
              // Check if preset avatar
              if (file_exists(__DIR__ . '/../assets/img/avatars/' . $avatar)) {
                echo '<img src="../assets/img/avatars/' . e($avatar) . '" alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover;">';
                $avatarFound = true;
              }
              // Check if custom avatar
              elseif (strpos($avatar, 'storage/avatars/') === 0 && file_exists(__DIR__ . '/../' . $avatar)) {
                echo '<img src="../' . e($avatar) . '" alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover;">';
                $avatarFound = true;
              }
            }
            
            if (!$avatarFound) {
              echo strtoupper(substr($user['name'][0] ?? 'U', 0, 1));
            }
          ?>
        </div>
        <div class="user-details">
          <p class="name"><?= e($user['name'] ?? 'User') ?></p>
          <p class="role"><?= ucfirst(e($user['role'] ?? 'user')) ?></p>
        </div>
      </div>
    </header>

    <main class="dashboard-content">
      <!-- Rain & Paper Plane Animation Canvas -->
      <canvas id="dashboardCanvas" aria-hidden="true"></canvas>
      <!-- Decorative Wave Background -->
      <svg viewBox="0 0 1440 120" class="wave-decoration" style="position: absolute; top: 0; left: 0; right: 0; fill: url(#waveGradient); opacity: 0.15;">
        <defs>
          <linearGradient id="waveGradient" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" style="stop-color:#2E7AE8;stop-opacity:1" />
            <stop offset="100%" style="stop-color:#0052CC;stop-opacity:1" />
          </linearGradient>
        </defs>
        <path d="M0,60 Q360,0 720,60 T1440,60 L1440,120 L0,120 Z" />
        <path d="M0,80 Q360,20 720,80 T1440,80 L1440,120 L0,120 Z" style="opacity: 0.7;" />
      </svg>

      <!-- Statistics Grid -->
      <div class="stats-grid" style="position: relative; z-index: 1;">
        <div class="stat-card">
          <div class="stat-icon primary">📧</div>
          <div class="stat-content">
            <h3>Total Kontak</h3>
            <p><?= $contacts_count ?></p>
            <span class="small">Email addresses</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon success">👥</div>
          <div class="stat-content">
            <h3>Total Grup</h3>
            <p><?= $groups_count ?></p>
            <span class="small">Contact groups</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon warning">📎</div>
          <div class="stat-content">
            <h3>Lampiran</h3>
            <p><?= $attachments_count ?></p>
            <span class="small">Attachment files</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon danger">👤</div>
          <div class="stat-content">
            <h3>Total User</h3>
            <p><?= $users_count ?></p>
            <span class="small">System accounts</span>
          </div>
        </div>
      </div>

      <!-- Two Column Layout for Charts -->
      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Activity Chart -->
        <div class="info-section">
          <h3 style="margin-top: 0; color: #0052CC;">📊 Email Activity (Last 7 Days)</h3>
          <div style="height: 250px; background: linear-gradient(135deg, rgba(0, 82, 204, 0.1), rgba(0, 63, 163, 0.1)); border-radius: 0.75rem; display: flex; align-items: flex-end; justify-content: center; padding: 20px; gap: 8px; position: relative;" id="activityChartContainer">
            <!-- Chart will be loaded here -->
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);"><p style="margin: 0; color: #9E9E9E; font-size: 0.9rem;">Loading...</p></div>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="info-section">
          <h3 style="margin-top: 0; color: #0052CC;">⚡ Quick Stats</h3>
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="padding: 1rem; background: linear-gradient(135deg, #E3F2FD, rgba(0, 82, 204, 0.1)); border-radius: 0.6rem; border-left: 3px solid #0052CC;">
              <p style="margin: 0 0 0.25rem 0; font-size: 0.85rem; color: #616161; font-weight: 700;">This Month</p>
              <p style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #0052CC;" id="statThisMonth">-</p>
            </div>
            <div style="padding: 1rem; background: linear-gradient(135deg, #FFF5E6, rgba(255, 184, 77, 0.1)); border-radius: 0.6rem; border-left: 3px solid #FFB84D;">
              <p style="margin: 0 0 0.25rem 0; font-size: 0.85rem; color: #616161; font-weight: 700;">Pending</p>
              <p style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #E67E22;" id="statPending">-</p>
            </div>
            <div style="padding: 1rem; background: linear-gradient(135deg, #E3F2FD, rgba(0, 82, 204, 0.1)); border-radius: 0.6rem; border-left: 3px solid #0052CC;">
              <p style="margin: 0 0 0.25rem 0; font-size: 0.85rem; color: #616161; font-weight: 700;">Contacts</p>
              <p style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #0052CC;" id="statContacts">-</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Documentation & Help Section -->
      <div class="info-section" style="background: linear-gradient(135deg, #E3F2FD 0%, #F0F7FF 100%); border: 1px solid #BBDEFB; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 2rem;">
        <h2 style="color: #0052CC; margin-top: 0;">📚 Documentation & Help</h2>
        <p style="color: #616161; margin: 0 0 1.5rem 0;">Baca panduan operasional lengkap untuk setiap modul aplikasi. Pilih dokumentasi yang Anda perlukan:</p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem;">
          <!-- Main Manual -->
          <div style="background: white; border-radius: 0.6rem; padding: 1.5rem; border-left: 4px solid #0052CC; box-shadow: 0 2px 4px rgba(0, 82, 204, 0.1); transition: all 0.3s ease;" class="doc-card">
            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
              <span style="font-size: 2rem;">📖</span>
              <div style="flex: 1;">
                <h4 style="margin: 0 0 0.5rem 0; color: #0052CC;">Manual Operasional Lengkap</h4>
                <p style="margin: 0; font-size: 0.85rem; color: #9E9E9E;">Panduan komprehensif 16 modul</p>
              </div>
            </div>
            <p style="margin: 0 0 1rem 0; font-size: 0.9rem; color: #616161;">
              Daftar isi lengkap, tips, best practices, troubleshooting, dan quick reference untuk seluruh aplikasi.
            </p>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
              <a href="../MANUAL_OPERASIONAL.md" download class="btn" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none; flex: 1; text-align: center;">📥 Download</a>
              <a href="help.php?doc=manual" class="btn secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none; flex: 1; text-align: center;">👁️ View Online</a>
            </div>
          </div>

          <!-- Module 1: Kontak -->
          <div style="background: white; border-radius: 0.6rem; padding: 1.5rem; border-left: 4px solid #4CAF50; box-shadow: 0 2px 4px rgba(76, 175, 80, 0.1); transition: all 0.3s ease;" class="doc-card">
            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
              <span style="font-size: 2rem;">👥</span>
              <div style="flex: 1;">
                <h4 style="margin: 0 0 0.5rem 0; color: #4CAF50;">Modul 1: Manajemen Kontak</h4>
                <p style="margin: 0; font-size: 0.85rem; color: #9E9E9E;">Add, edit, delete, import kontak</p>
              </div>
            </div>
            <p style="margin: 0 0 1rem 0; font-size: 0.9rem; color: #616161;">
              CRUD operations, bulk import CSV/Excel, search & filter, export data, dan troubleshooting kontak.
            </p>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
              <a href="../MODUL_1_MANAJEMEN_KONTAK.md" download class="btn success" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none; flex: 1; text-align: center;">📥 Download</a>
              <a href="help.php?doc=modul1" class="btn secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none; flex: 1; text-align: center;">👁️ View</a>
            </div>
          </div>

          <!-- Module 2: Compose Email -->
          <div style="background: white; border-radius: 0.6rem; padding: 1.5rem; border-left: 4px solid #2196F3; box-shadow: 0 2px 4px rgba(33, 150, 243, 0.1); transition: all 0.3s ease;" class="doc-card">
            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
              <span style="font-size: 2rem;">✉️</span>
              <div style="flex: 1;">
                <h4 style="margin: 0 0 0.5rem 0; color: #2196F3;">Modul 2: Compose & Send Email</h4>
                <p style="margin: 0; font-size: 0.85rem; color: #9E9E9E;">Email composition, sending, tracking</p>
              </div>
            </div>
            <p style="margin: 0 0 1rem 0; font-size: 0.9rem; color: #616161;">
              Rich editor, select recipients, upload attachment, similarity matching, send process, best practices.
            </p>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
              <a href="../MODUL_2_COMPOSE_EMAIL.md" download class="btn primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none; flex: 1; text-align: center;">📥 Download</a>
              <a href="help.php?doc=modul2" class="btn secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none; flex: 1; text-align: center;">👁️ View</a>
            </div>
          </div>

          <!-- Online Help -->
          <div style="background: white; border-radius: 0.6rem; padding: 1.5rem; border-left: 4px solid #FF9800; box-shadow: 0 2px 4px rgba(255, 152, 0, 0.1); transition: all 0.3s ease;" class="doc-card">
            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
              <span style="font-size: 2rem;">❓</span>
              <div style="flex: 1;">
                <h4 style="margin: 0 0 0.5rem 0; color: #FF9800;">FAQ & Troubleshooting</h4>
                <p style="margin: 0; font-size: 0.85rem; color: #9E9E9E;">Jawaban pertanyaan umum</p>
              </div>
            </div>
            <p style="margin: 0 0 1rem 0; font-size: 0.9rem; color: #616161;">
              Solusi untuk masalah umum, tips & trik, common mistakes, dan best practices.
            </p>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
              <a href="help.php?section=faq" class="btn warn" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none; flex: 1; text-align: center;">👁️ View FAQ</a>
              <a href="help.php?section=troubleshoot" class="btn secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none; flex: 1; text-align: center;">🔧 Troubleshoot</a>
            </div>
          </div>

          <!-- Quick Start -->
          <div style="background: white; border-radius: 0.6rem; padding: 1.5rem; border-left: 4px solid #009688; box-shadow: 0 2px 4px rgba(0, 150, 136, 0.1); transition: all 0.3s ease;" class="doc-card">
            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
              <span style="font-size: 2rem;">🚀</span>
              <div style="flex: 1;">
                <h4 style="margin: 0 0 0.5rem 0; color: #009688;">Quick Start Guide</h4>
                <p style="margin: 0; font-size: 0.85rem; color: #9E9E9E;">Mulai dalam 5 menit</p>
              </div>
            </div>
            <p style="margin: 0 0 1rem 0; font-size: 0.9rem; color: #616161;">
              Panduan singkat untuk user baru. Dari login hingga send email pertama Anda.
            </p>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
              <a href="help.php?doc=quickstart" class="btn" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none; flex: 1; text-align: center; background: #009688;">👁️ Start Now</a>
            </div>
          </div>

          <!-- Video Tutorials (Future) -->
          <div style="background: white; border-radius: 0.6rem; padding: 1.5rem; border-left: 4px solid #E91E63; box-shadow: 0 2px 4px rgba(233, 30, 99, 0.1); opacity: 0.7;" class="doc-card">
            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
              <span style="font-size: 2rem;">🎥</span>
              <div style="flex: 1;">
                <h4 style="margin: 0 0 0.5rem 0; color: #E91E63;">Video Tutorials</h4>
                <p style="margin: 0; font-size: 0.85rem; color: #9E9E9E;">Coming soon...</p>
              </div>
            </div>
            <p style="margin: 0 0 1rem 0; font-size: 0.9rem; color: #616161;">
              Tutorial video step-by-step untuk setiap fitur utama aplikasi.
            </p>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
              <button disabled class="btn secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; flex: 1; text-align: center; opacity: 0.5;">🔜 Coming Soon</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Configuration Section -->
      <div class="info-section">
        <h2 style="color: #0052CC;">⚙️ Konfigurasi Sistem</h2>
        <div class="info-list">
          <div class="info-item" style="border-left: 3px solid #0052CC;">
            <strong style="color: #0052CC;">📁 Folder Lampiran:</strong><br>
            <code style="background: #F5F8FF; color: #0052CC;"><?= e(ATTACHMENTS_DIR) ?></code>
            <p class="small" style="color: #616161;">Letakkan file lampiran Anda di folder ini atau upload dari menu "Upload Lampiran".</p>
          </div>
          <div class="info-item" style="border-left: 3px solid #0052CC;">
            <strong style="color: #0052CC;">📧 Akun Outlook:</strong><br>
            <code style="background: #F5F8FF; color: #0052CC;"><?= e(get_sender_account()) ?></code>
            <p class="small" style="color: #616161;">Account yang digunakan untuk mengirim email melalui Outlook COM.</p>
          </div>
        </div>

        <div class="quick-actions">
          <a href="contacts.php" class="btn">📇 Kelola Kontak</a>
          <a href="compose.php" class="btn success">✉️ Kirim Email</a>
          <a href="upload.php" class="btn warn">⬆️ Upload File</a>
          <a href="settings.php" class="btn secondary">⚙️ Pengaturan</a>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
// Active menu
document.querySelectorAll('.sidebar-nav a').forEach(link => {
  if (link.getAttribute('href') === window.location.pathname.split('/').pop()) {
    link.classList.add('active');
  }
});

// Dashboard Stats - Real-time Update
function loadDashboardStats() {
  fetch('api_dashboard_stats.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update stats
        document.getElementById('statThisMonth').textContent = data.this_month + ' Email';
        document.getElementById('statPending').textContent = data.pending + ' Task';
        document.getElementById('statContacts').textContent = data.contacts + ' Contact';
        
        // Render chart
        renderActivityChart(data.activity);
      }
    })
    .catch(err => console.log('Stats load error:', err));
}

// Simple bar chart renderer
function renderActivityChart(data) {
  const container = document.getElementById('activityChartContainer');
  if (!container) return;
  
  const max = data.values.length > 0 ? Math.max(...data.values, 1) : 1;
  const width = Math.max(40, (100 / data.values.length) - 2);
  
  container.innerHTML = '';
  
  data.dates.forEach((date, index) => {
    const value = data.values[index];
    const height = (value / max) * 100; // percentage of max
    const heightPx = Math.max(height > 0 ? 20 : 10, height * 1.8); // min height, scale by 1.8
    
    const bar = document.createElement('div');
    bar.style.cssText = `
      width: ${width}%;
      height: 0;
      background: linear-gradient(180deg, #0052CC, #003fa3);
      border-radius: 4px 4px 0 0;
      position: relative;
      transition: height 0.3s ease;
      min-width: 20px;
      cursor: pointer;
      opacity: 0.8;
    `;
    bar.title = date + ': ' + value + ' email';
    bar.onmouseover = () => {
      bar.style.opacity = '1';
      bar.style.boxShadow = '0 -4px 12px rgba(0, 82, 204, 0.3)';
    };
    bar.onmouseout = () => {
      bar.style.opacity = '0.8';
      bar.style.boxShadow = '';
    };
    
    container.appendChild(bar);
  });
  
  // Animate bars
  setTimeout(() => {
    const bars = container.querySelectorAll('div');
    bars.forEach((bar, index) => {
      const value = data.values[index];
      const height = (value / max) * 100;
      const heightPx = Math.max(height > 0 ? 20 : 10, height * 1.8);
      setTimeout(() => {
        bar.style.height = heightPx + 'px';
      }, index * 50);
    });
  }, 100);
}

// Load stats on page load
loadDashboardStats();

// Auto-refresh every 30 seconds
setInterval(loadDashboardStats, 30000);
</script>
</script>

<!-- Avatar Modal -->
<div class="avatar-modal" id="avatarModal">
  <div class="avatar-modal-content">
    <div class="avatar-modal-header">
      <h2>Ubah Avatar</h2>
      <button class="avatar-modal-close" onclick="document.getElementById('avatarModal').classList.remove('active')">✕</button>
    </div>
    
    <h3 style="color: #0052CC; margin: 1.5rem 0 1rem 0; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Pilih Avatar</h3>
    <div class="avatar-grid">
      <div class="avatar-option" data-avatar="avatar1.svg">
        <img src="../assets/img/avatars/avatar1.svg" alt="Avatar 1">
      </div>
      <div class="avatar-option" data-avatar="avatar2.svg">
        <img src="../assets/img/avatars/avatar2.svg" alt="Avatar 2">
      </div>
      <div class="avatar-option" data-avatar="avatar3.svg">
        <img src="../assets/img/avatars/avatar3.svg" alt="Avatar 3">
      </div>
      <div class="avatar-option" data-avatar="avatar4.svg">
        <img src="../assets/img/avatars/avatar4.svg" alt="Avatar 4">
      </div>
      <div class="avatar-option" data-avatar="avatar5.svg">
        <img src="../assets/img/avatars/avatar5.svg" alt="Avatar 5">
      </div>
      <div class="avatar-option" data-avatar="avatar6.svg">
        <img src="../assets/img/avatars/avatar6.svg" alt="Avatar 6">
      </div>
    </div>
    
    <div class="avatar-upload-section">
      <h3>Upload Avatar Custom</h3>
      <label class="avatar-upload-btn">
        📤 Pilih File (JPG, PNG, SVG)
        <input type="file" class="avatar-upload-input" accept="image/*">
      </label>
      <small style="color: #616161; display: block; margin-top: 0.5rem;">Max 5MB • Format: JPG, PNG, SVG, WebP</small>
    </div>
    
    <div class="avatar-actions">
      <button class="btn-cancel" id="avatarCancelBtn">Batal</button>
      <button class="btn-save" id="avatarSaveBtn" disabled>Simpan</button>
    </div>
  </div>
</div>

<!-- Initialize Avatar Modal -->
<script>
(function() {
  let selectedAvatar = null;
  let uploadedFile = null;

  // Avatar button click
  const userAvatarBtn = document.getElementById('userAvatarBtn');
  if (userAvatarBtn) {
    userAvatarBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const modal = document.getElementById('avatarModal');
      if (modal) {
        modal.classList.add('active');
      }
    });
  }

  // Avatar selection (preset avatars)
  const avatarOptions = document.querySelectorAll('.avatar-option');
  avatarOptions.forEach(option => {
    option.addEventListener('click', function() {
      document.querySelectorAll('.avatar-option').forEach(o => {
        o.classList.remove('selected');
      });
      this.classList.add('selected');
      selectedAvatar = this.dataset.avatar;
      uploadedFile = null;
      
      // Enable save button
      const saveBtn = document.getElementById('avatarSaveBtn');
      if (saveBtn) {
        saveBtn.disabled = false;
      }
    });
  });

  // File upload input
  const uploadInput = document.querySelector('.avatar-upload-input');
  if (uploadInput) {
    uploadInput.addEventListener('change', function() {
      if (this.files && this.files.length > 0) {
        handleFileUpload(this.files[0]);
      }
    });
  }

  // Handle file upload
  function handleFileUpload(file) {
    // Validasi file
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];

    if (file.size > maxSize) {
      alert('File terlalu besar. Maksimal 5MB');
      return;
    }

    if (!allowedTypes.includes(file.type)) {
      alert('Format file tidak didukung. Gunakan JPG, PNG, SVG, atau WebP');
      return;
    }

    // Deselect preset avatars
    document.querySelectorAll('.avatar-option').forEach(o => {
      o.classList.remove('selected');
    });

    // Show preview
    const reader = new FileReader();
    reader.onload = function(e) {
      selectedAvatar = null;
      uploadedFile = file;

      // Upload file
      uploadToServer(file, e.target.result);
    };
    reader.readAsDataURL(file);
  }

  // Upload file to server
  function uploadToServer(file, preview) {
    const formData = new FormData();
    formData.append('avatar', file);

    const uploadBtn = document.querySelector('.avatar-upload-btn');
    const originalText = uploadBtn ? uploadBtn.textContent : '';
    if (uploadBtn) {
      uploadBtn.textContent = '⏳ Uploading...';
    }

    fetch('api_upload_avatar.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('Server returned: ' + response.status + ' ' + response.statusText);
      }
      return response.json();
    })
    .then(data => {
      if (uploadBtn) {
        uploadBtn.textContent = originalText;
      }

      if (data.success) {
        selectedAvatar = data.avatar;
        uploadedFile = null;

        // Show preview in modal
        if (uploadBtn) {
          uploadBtn.innerHTML = '✓ File uploaded: <br><strong>' + file.name + '</strong>';
          uploadBtn.style.background = '#D1FAE5';
          uploadBtn.style.borderColor = '#10B981';
          uploadBtn.style.color = '#065F46';
          uploadBtn.style.borderStyle = 'solid';
        }

        // Enable save button
        const saveBtn = document.getElementById('avatarSaveBtn');
        if (saveBtn) {
          saveBtn.disabled = false;
        }
      } else {
        const errorMsg = data.error || 'Gagal upload file';
        alert('Error upload: ' + errorMsg);
        console.error('Upload error:', data);
        if (uploadBtn) {
          uploadBtn.innerHTML = '📤 Pilih File (JPG, PNG, SVG)';
          uploadBtn.style.background = '';
          uploadBtn.style.borderColor = '';
          uploadBtn.style.color = '';
        }
      }
    })
    .catch(err => {
      console.error('Upload error:', err);
      alert('Terjadi kesalahan saat upload: ' + err.message + '\n\nPastikan server berjalan dan folder storage/avatars dapat ditulis.');
      if (uploadBtn) {
        uploadBtn.textContent = originalText;
        uploadBtn.style.background = '';
        uploadBtn.style.borderColor = '';
        uploadBtn.style.color = '';
      }
    });
  }

  // Save avatar button
  const avatarSaveBtn = document.getElementById('avatarSaveBtn');
  if (avatarSaveBtn) {
    avatarSaveBtn.addEventListener('click', function() {
      if (!selectedAvatar) {
        alert('Pilih atau upload avatar terlebih dahulu');
        return;
      }
      
      this.disabled = true;
      const originalText = this.textContent;
      this.textContent = 'Menyimpan...';
      
      const isPreset = selectedAvatar.endsWith('.svg');
      const avatarPath = isPreset ? 
        '../assets/img/avatars/' + selectedAvatar : 
        '../' + selectedAvatar;

      fetch('api_update_avatar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ avatar: selectedAvatar })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update avatar display
          const avatarBtn = document.getElementById('userAvatarBtn');
          if (avatarBtn) {
            avatarBtn.innerHTML = '<img src="' + avatarPath + '" alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover;">';
          }
          
          const modal = document.getElementById('avatarModal');
          if (modal) {
            modal.classList.remove('active');
          }
          
          alert('Avatar berhasil diubah!');
          selectedAvatar = null;
          uploadedFile = null;
          
          // Reset upload button
          const uploadBtn = document.querySelector('.avatar-upload-btn');
          if (uploadBtn) {
            uploadBtn.innerHTML = '📤 Pilih File (JPG, PNG, SVG)';
            uploadBtn.style.background = '';
            uploadBtn.style.borderColor = '';
            uploadBtn.style.color = '';
          }

          // Reset button
          this.disabled = true;
          this.textContent = originalText;
        } else {
          alert('Error: ' + (data.error || 'Gagal mengubah avatar'));
          this.disabled = false;
          this.textContent = originalText;
        }
      })
      .catch(err => {
        console.error('Avatar save error:', err);
        alert('Terjadi kesalahan: ' + err.message);
        this.disabled = false;
        this.textContent = originalText;
      });
    });
  }

  // Cancel button
  const avatarCancelBtn = document.getElementById('avatarCancelBtn');
  if (avatarCancelBtn) {
    avatarCancelBtn.addEventListener('click', function() {
      const modal = document.getElementById('avatarModal');
      if (modal) {
        modal.classList.remove('active');
      }
      selectedAvatar = null;
      uploadedFile = null;
      
      // Reset upload button
      const uploadBtn = document.querySelector('.avatar-upload-btn');
      if (uploadBtn) {
        uploadBtn.innerHTML = '📤 Pilih File (JPG, PNG, SVG)';
        uploadBtn.style.background = '';
        uploadBtn.style.borderColor = '';
        uploadBtn.style.color = '';
      }
    });
  }

  // Close modal when clicking outside
  const avatarModal = document.getElementById('avatarModal');
  if (avatarModal) {
    avatarModal.addEventListener('click', function(e) {
      if (e.target === this) {
        this.classList.remove('active');
      }
    });
  }
})();
</script>

<!-- Rain & Paper Plane Particle Animation -->
<script>
(() => {
  const canvas = document.getElementById('dashboardCanvas');
  if (!canvas || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const ctx = canvas.getContext('2d');
  let W, H, raf;

  function resize() {
    const rect = canvas.parentElement.getBoundingClientRect();
    W = canvas.width = rect.width;
    H = canvas.height = rect.height;
  }
  window.addEventListener('resize', resize, { passive: true });
  resize();

  // --- Rain drops ---
  const drops = [];
  const DROP_COUNT = 120;
  function spawnDrop() {
    return {
      x: Math.random() * W,
      y: Math.random() * -H,
      len: 12 + Math.random() * 18,
      speed: 4 + Math.random() * 6,
      opacity: 0.15 + Math.random() * 0.25,
      wind: 1.5 + Math.random() * 1
    };
  }
  for (let i = 0; i < DROP_COUNT; i++) {
    const d = spawnDrop();
    d.y = Math.random() * H;
    drops.push(d);
  }

  // --- Paper planes (origami) ---
  const planes = [];
  const PLANE_COUNT = 8;
  function spawnPlane() {
    const size = 10 + Math.random() * 14;
    return {
      x: -40,
      y: 60 + Math.random() * (H - 120),
      size: size,
      speedX: 1.2 + Math.random() * 2,
      speedY: -0.4 + Math.random() * 0.8,
      wobbleAmp: 8 + Math.random() * 12,
      wobbleFreq: 0.02 + Math.random() * 0.02,
      angle: -0.25 + Math.random() * 0.15,
      opacity: 0.35 + Math.random() * 0.35,
      phase: Math.random() * Math.PI * 2,
      t: 0
    };
  }
  for (let i = 0; i < PLANE_COUNT; i++) {
    const p = spawnPlane();
    p.x = Math.random() * W;
    p.t = Math.random() * 400;
    planes.push(p);
  }

  function drawPlane(p) {
    ctx.save();
    const wobble = Math.sin(p.phase + p.t * p.wobbleFreq) * p.wobbleAmp;
    const dy = p.y + wobble;
    ctx.translate(p.x, dy);
    ctx.rotate(p.angle + Math.sin(p.phase + p.t * 0.03) * 0.15);
    ctx.globalAlpha = p.opacity;

    const s = p.size;
    // Origami paper plane shape
    ctx.beginPath();
    ctx.moveTo(0, 0);           // nose
    ctx.lineTo(-s * 1.2, -s * 0.45);
    ctx.lineTo(-s * 0.6, 0);
    ctx.lineTo(-s * 1.2, s * 0.45);
    ctx.closePath();
    ctx.fillStyle = 'rgba(255,255,255,0.7)';
    ctx.fill();
    ctx.strokeStyle = 'rgba(0,82,204,0.5)';
    ctx.lineWidth = 0.8;
    ctx.stroke();

    // Center fold line
    ctx.beginPath();
    ctx.moveTo(0, 0);
    ctx.lineTo(-s * 0.6, 0);
    ctx.strokeStyle = 'rgba(0,82,204,0.35)';
    ctx.lineWidth = 0.6;
    ctx.stroke();

    // Small trail
    ctx.beginPath();
    ctx.moveTo(-s * 1.2, 0);
    ctx.lineTo(-s * 1.2 - s * 0.8, Math.sin(p.phase + p.t * 0.05) * 3);
    ctx.strokeStyle = 'rgba(0,82,204,0.12)';
    ctx.lineWidth = 0.5;
    ctx.stroke();

    ctx.restore();
  }

  function frame() {
    ctx.clearRect(0, 0, W, H);

    // Draw rain
    for (let i = 0; i < drops.length; i++) {
      const d = drops[i];
      ctx.beginPath();
      ctx.moveTo(d.x, d.y);
      ctx.lineTo(d.x + d.wind * 2, d.y + d.len);
      ctx.strokeStyle = `rgba(174,194,224,${d.opacity})`;
      ctx.lineWidth = 1;
      ctx.stroke();

      d.x += d.wind;
      d.y += d.speed;
      if (d.y > H + 20) {
        d.y = Math.random() * -60;
        d.x = Math.random() * W;
      }
    }

    // Draw planes
    for (let i = 0; i < planes.length; i++) {
      const p = planes[i];
      drawPlane(p);
      p.x += p.speedX;
      p.t++;
      if (p.x > W + 60) {
        Object.assign(p, spawnPlane());
      }
    }

    raf = requestAnimationFrame(frame);
  }
  raf = requestAnimationFrame(frame);

  window.addEventListener('beforeunload', () => { if (raf) cancelAnimationFrame(raf); });
})();
</script>

<!-- Page Transition Overlay -->
<div class="page-transition" id="pageTransition"></div>

<script>
// Page Transition Animation
function initPageTransitions() {
  // Get all navigation links
  const navLinks = document.querySelectorAll('.sidebar-nav a, .quick-actions a, main a[href$=".php"]');
  
  navLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      
      // Don't animate for hash links or external URLs
      if (!href || href.startsWith('#') || href.startsWith('http')) {
        return;
      }
      
      // Prevent default navigation
      e.preventDefault();
      
      // Get transition elements
      const overlay = document.getElementById('pageTransition');
      const mainContent = document.querySelector('.main-content');
      const dashboard = document.querySelector('.dashboard-content');
      
      if (overlay) {
        // Trigger fade out animation
        overlay.classList.add('active');
        
        if (mainContent) mainContent.classList.add('transitioning');
        if (dashboard) dashboard.classList.add('transitioning');
        
        // Navigate after animation
        setTimeout(() => {
          window.location.href = href;
        }, 300);
      } else {
        // Fallback if overlay doesn't exist
        window.location.href = href;
      }
    });
  });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', initPageTransitions);

// Also handle initial page load animation
window.addEventListener('load', function() {
  const overlay = document.getElementById('pageTransition');
  const mainContent = document.querySelector('.main-content');
  const dashboard = document.querySelector('.dashboard-content');
  
  if (overlay) {
    overlay.classList.remove('active');
    if (mainContent) mainContent.classList.remove('transitioning');
    if (dashboard) dashboard.classList.remove('transitioning');
  }
});
</script>
