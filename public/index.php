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
  /* THEME VARIABLES - Light Theme Only */
  :root {
    --bg: #F8FAFC;
    --card: white;
    --text: #0F172A;
    --muted: #64748B;
    --border: #E2E8F0;
  }

  body {
    color: var(--text);
    background: var(--bg);
  }

  /* Dashboard Grid Layout */
  .dashboard-wrapper {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    grid-template-rows: 1fr;
    gap: 0;
    min-height: 100vh;
  }

  /* Sidebar Navigation - Blue Theme */
  .sidebar {
    background: linear-gradient(180deg, rgba(29, 78, 216, 0.95) 0%, rgba(30, 64, 175, 0.95) 100%);
    color: white;
    padding: 2rem 0;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    box-shadow: 2px 0 8px rgba(0, 82, 204, 0.15);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
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
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 500;
    border-left: 3px solid transparent;
    position: relative;
    overflow: hidden;
  }

  .sidebar-nav a::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 0;
    height: 100%;
    background: linear-gradient(90deg, rgba(255,255,255,0.1) 0%, transparent 100%);
    transition: width 0.3s ease;
  }

  .sidebar-nav a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: white;
    transform: translateX(4px);
  }

  .sidebar-nav a:hover::before {
    width: 100%;
  }

  .sidebar-nav a.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-left-color: white;
    box-shadow: inset 4px 0 0 rgba(255,255,255,0.2);
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
    min-width: 0;
    overflow-x: hidden;
  }

  .dashboard-header {
    background: rgba(255, 255, 255, 0.9);
    border-bottom: 1px solid rgba(226, 232, 240, 0.6);
    padding: 1.25rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 100;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    color: #0F172A;
  }

  .dashboard-header h1 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--color-primary-700);
    font-weight: 700;
  }

  .header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .dark-mode-toggle {
    background: var(--color-slate-100);
    border: 1.5px solid var(--color-slate-200);
    border-radius: var(--radius-lg);
    padding: 0.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    color: var(--color-slate-600);
  }

  .dark-mode-toggle:hover {
    background: var(--color-slate-200);
    color: var(--color-slate-800);
    transform: scale(1.05);
  }

  .dark-mode-toggle svg {
    width: 20px;
    height: 20px;
  }

  .notification-bell {
    position: relative;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
    flex-shrink: 0;
  }

  .notification-bell:hover {
    background: rgba(37, 99, 235, 0.1);
  }

  .notification-bell svg {
    width: 20px;
    height: 20px;
    color: var(--color-slate-600);
    transition: all 0.2s ease;
  }

  .notification-bell:hover svg {
    color: var(--color-primary-600);
  }

  .notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: var(--color-danger-500);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 1px 4px;
    border-radius: 9999px;
    min-width: 16px;
    text-align: center;
  }

  @keyframes pulse {
    0%, 100% {
      transform: scale(1);
    }
    50% {
      transform: scale(1.1);
    }
  }

  .search-container {
    position: relative;
    width: 300px;
  }

  .search-input {
    width: 100%;
    padding: 0.625rem 1rem 0.625rem 2.5rem;
    border: 1.5px solid var(--color-slate-200);
    border-radius: var(--radius-lg);
    font-size: 0.9rem;
    background: var(--color-slate-50);
    color: var(--color-slate-800);
    transition: all 0.2s ease;
  }

  .search-input:focus {
    outline: none;
    border-color: var(--color-primary-500);
    background: var(--card);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }

  .search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: var(--color-slate-400);
    pointer-events: none;
  }

  .user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--color-primary-600), var(--color-primary-800));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    overflow: hidden;
    position: relative;
  }

  .user-avatar::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 50%);
    opacity: 0;
    transition: opacity 0.3s ease;
  }

  .user-avatar:hover {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
    border-color: rgba(255, 255, 255, 0.3);
  }

  .user-avatar:hover::after {
    opacity: 1;
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
    color: var(--color-primary-700);
  }

  .user-details .role {
    font-size: 0.8rem;
    color: var(--muted);
    font-weight: 600;
  }

  .dashboard-content {
    padding: 2rem;
    flex: 1;
    background: #F8FAFC;
    color: #0F172A;
  }

  /* Stats Grid */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
  }

  .stat-card {
    background: rgba(255, 255, 255, 0.8);
    border-radius: 0.875rem;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: var(--transition);
    display: flex;
    align-items: flex-start;
    gap: 1.25rem;
    color: #0F172A;
  }

  .stat-card:hover {
    box-shadow: 0 8px 24px rgba(37, 99, 235, 0.15);
    border-color: var(--color-primary-500);
    transform: translateY(-4px) scale(1.02);
  }

  .stat-card .stat-icon {
    transition: transform 0.3s ease;
  }

  .stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(5deg);
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
    color: var(--color-primary-700);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .stat-content p {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--color-primary-700);
  }

  .stat-content .small {
    font-size: 0.8rem;
    color: var(--muted);
    margin-top: 0.25rem;
    font-weight: 600;
  }

  .trend-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    margin-top: 0.5rem;
  }

  .trend-up {
    background: rgba(16, 185, 129, 0.1);
    color: var(--color-success-600);
  }

  .trend-down {
    background: rgba(220, 38, 38, 0.1);
    color: var(--color-danger-600);
  }

  .trend-neutral {
    background: rgba(100, 116, 139, 0.1);
    color: var(--color-slate-600);
  }

  .trend-indicator svg {
    width: 14px;
    height: 14px;
  }

  .info-section {
    background: rgba(255, 255, 255, 0.8);
    border-radius: 0.875rem;
    padding: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: #0F172A;
  }

  .info-section h2 {
    margin-top: 0;
    border-bottom: 2px solid var(--color-primary-200);
    padding-bottom: 1rem;
    color: var(--color-primary-700);
  }

  .info-section h3 {
    margin: 0 0 1.5rem 0;
    color: var(--color-primary-700);
  }

  .info-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
  }

  .info-item {
    padding: 1rem;
    border-left: 3px solid var(--color-primary-600);
  }

  .info-item strong {
    color: var(--color-primary-700);
  }

  .info-item code {
    background: var(--color-primary-50);
    padding: 0.4rem 0.8rem;
    border-radius: 0.4rem;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    color: var(--color-primary-700);
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


  /* Responsive */
  @media (max-width: 1024px) {
    .dashboard-wrapper {
      grid-template-columns: 200px minmax(0, 1fr);
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
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    color: #0F172A;
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
    color: var(--color-primary-700);
    font-size: 1.25rem;
  }

  .avatar-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--muted);
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .avatar-modal-close:hover {
    color: var(--color-primary-700);
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
    border: 2px solid var(--color-primary-200);
    border-radius: 0.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: white;
    transition: all 0.2s ease;
    overflow: hidden;
    color: #0F172A;
  }

  .avatar-option:hover {
    border-color: var(--color-primary-600);
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
  }

  .avatar-option.selected {
    border-color: var(--color-primary-600);
    background: rgba(37, 99, 235, 0.1);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
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
    color: var(--color-primary-700);
  }

  .avatar-upload-section {
    border-top: 1px solid var(--color-primary-200);
    padding-top: 1.5rem;
    margin-bottom: 1.5rem;
  }

  .avatar-upload-section h3 {
    margin-top: 0;
    color: var(--color-primary-700);
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
    background: var(--color-primary-100);
    border: 2px dashed var(--color-primary-600);
    border-radius: 0.5rem;
    color: var(--color-primary-700);
    cursor: pointer;
    text-align: center;
    font-weight: 600;
    transition: all 0.2s ease;
  }

  .avatar-upload-btn:hover {
    background: rgba(0, 82, 204, 0.15);
    border-color: var(--color-primary-800);
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
    background: var(--color-slate-100);
    color: var(--muted);
  }

  .avatar-actions .btn-cancel:hover {
    background: var(--color-slate-200);
  }

  .avatar-actions .btn-save {
    background: var(--color-primary-600);
    color: white;
  }

  .avatar-actions .btn-save:hover {
    background: var(--color-primary-700);
  }

  .avatar-actions .btn-save:disabled {
    background: var(--color-slate-300);
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
      <div class="header-actions">
        <div class="notification-bell">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <span class="notification-badge">3</span>
        </div>
        <div class="user-avatar" onclick="document.getElementById('avatarModal').classList.add('active')">
          <img src="../assets/img/avatars/avatar1.svg" alt="User Avatar">
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
        </div>
        <div class="user-details">
          <p class="name"><?= e($user['name'] ?? 'User') ?></p>
          <p class="role"><?= ucfirst(e($user['role'] ?? 'user')) ?></p>
        </div>
      </div>
    </header>

    <main class="dashboard-content">
      <!-- Statistics Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon primary">📧</div>
          <div class="stat-content">
            <h3>Total Kontak</h3>
            <p><?= $contacts_count ?></p>
            <span class="small">Email addresses</span>
            <div class="trend-indicator trend-up">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 15l-6-6-6 6"/>
              </svg>
              +12.5%
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon success">👥</div>
          <div class="stat-content">
            <h3>Total Grup</h3>
            <p><?= $groups_count ?></p>
            <span class="small">Contact groups</span>
            <div class="trend-indicator trend-up">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 15l-6-6-6 6"/>
              </svg>
              +8.2%
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon warning">📎</div>
          <div class="stat-content">
            <h3>Lampiran</h3>
            <p><?= $attachments_count ?></p>
            <span class="small">Attachment files</span>
            <div class="trend-indicator trend-neutral">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M5 12h14"/>
              </svg>
              0%
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon danger">👤</div>
          <div class="stat-content">
            <h3>Total User</h3>
            <p><?= $users_count ?></p>
            <span class="small">System accounts</span>
            <div class="trend-indicator trend-up">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 15l-6-6-6 6"/>
              </svg>
              +5.0%
            </div>
          </div>
        </div>
      </div>

      <!-- Two Column Layout for Charts -->
      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Activity Chart -->
        <div class="info-section">
          <h3 style="margin-top: 0; color: var(--color-primary-700);">📊 Email Activity (Last 7 Days)</h3>
          <div style="height: 250px; background: linear-gradient(135deg, rgba(0, 82, 204, 0.1), rgba(0, 63, 163, 0.1)); border-radius: 0.75rem; display: flex; align-items: flex-end; justify-content: center; padding: 20px; gap: 8px; position: relative;" id="activityChartContainer">
            <!-- Chart will be loaded here -->
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);"><p style="margin: 0; color: var(--muted); font-size: 0.9rem;">Loading...</p></div>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="info-section">
          <h3 style="margin-top: 0; color: var(--color-primary-700);">⚡ Quick Stats</h3>
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="padding: 1rem; background: linear-gradient(135deg, var(--color-primary-100), rgba(0, 82, 204, 0.1)); border-radius: 0.6rem; border-left: 3px solid var(--color-primary-500);">
              <p style="margin: 0 0 0.25rem 0; font-size: 0.85rem; color: var(--muted); font-weight: 700;">This Month</p>
              <p style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-primary-700);" id="statThisMonth">-</p>
            </div>
            <div style="padding: 1rem; background: linear-gradient(135deg, var(--color-warning-100), rgba(255, 184, 77, 0.1)); border-radius: 0.6rem; border-left: 3px solid var(--color-warning-500);">
              <p style="margin: 0 0 0.25rem 0; font-size: 0.85rem; color: var(--muted); font-weight: 700;">Pending</p>
              <p style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-warning-700);" id="statPending">-</p>
            </div>
            <div style="padding: 1rem; background: linear-gradient(135deg, var(--color-primary-100), rgba(0, 82, 204, 0.1)); border-radius: 0.6rem; border-left: 3px solid var(--color-primary-500);">
              <p style="margin: 0 0 0.25rem 0; font-size: 0.85rem; color: var(--muted); font-weight: 700;">Contacts</p>
              <p style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-primary-700);" id="statContacts">-</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Documentation & Help Section - Modern Redesign -->
      <div class="info-section docs-section" style="background: linear-gradient(145deg, #f8fafc 0%, #e2e8f0 100%); border: none; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <!-- Header with Search -->
        <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 2px solid var(--color-primary-200);">
          <div style="flex: 1; min-width: 280px;">
            <h2 style="color: var(--color-primary-800); margin: 0; font-size: 1.75rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
              <span style="font-size: 2rem;">📚</span>
              Documentation & Help Center
            </h2>
            <p style="color: var(--muted); margin: 0.5rem 0 0 0; font-size: 1rem;">Temukan panduan, tutorial, dan solusi untuk aplikasi Email Dispatcher</p>
          </div>
          <!-- Search Bar -->
          <div style="flex: 0 1 400px; min-width: 280px;">
            <div style="position: relative;">
              <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1.1rem;">🔍</span>
              <input type="text" id="docSearch" placeholder="Cari dokumentasi..." style="width: 100%; padding: 0.875rem 1rem 0.875rem 2.75rem; border: 2px solid var(--color-primary-200); border-radius: 0.75rem; font-size: 1rem; background: white; transition: all 0.3s ease; box-sizing: border-box;" onfocus="this.style.borderColor='var(--color-primary-500)'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)';" onblur="this.style.borderColor='var(--color-primary-200)'; this.style.boxShadow='none';">
            </div>
          </div>
        </div>

        <!-- Category Tabs -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
          <button onclick="filterDocs('all')" class="doc-tab active" data-category="all" style="padding: 0.625rem 1.25rem; border: 2px solid var(--color-primary-500); background: var(--color-primary-500); color: white; border-radius: 2rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-size: 0.9rem;">Semua</button>
          <button onclick="filterDocs('getting-started')" class="doc-tab" data-category="getting-started" style="padding: 0.625rem 1.25rem; border: 2px solid var(--color-primary-200); background: white; color: var(--color-primary-700); border-radius: 2rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: 0.9rem;">🚀 Getting Started</button>
          <button onclick="filterDocs('modules')" class="doc-tab" data-category="modules" style="padding: 0.625rem 1.25rem; border: 2px solid var(--color-primary-200); background: white; color: var(--color-primary-700); border-radius: 2rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: 0.9rem;">📖 Modul</button>
          <button onclick="filterDocs('help')" class="doc-tab" data-category="help" style="padding: 0.625rem 1.25rem; border: 2px solid var(--color-primary-200); background: white; color: var(--color-primary-700); border-radius: 2rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: 0.9rem;">❓ Bantuan</button>
        </div>

        <!-- Featured Cards - Getting Started -->
        <div style="margin-bottom: 2rem;">
          <h3 style="color: var(--color-primary-700); font-size: 1.1rem; font-weight: 600; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;" class="category-header" data-category="getting-started">
            <span style="background: linear-gradient(135deg, #10b981, #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">🚀 Mulai Cepat</span>
          </h3>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;" class="doc-grid">
            <!-- Quick Start -->
            <div class="doc-card-modern" data-category="getting-started" data-keywords="quick start mulai cepat baru beginner" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-radius: 1rem; padding: 1.75rem; border: 2px solid #10b981; transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden;">
              <div style="position: absolute; top: 0; right: 0; background: #10b981; color: white; padding: 0.375rem 0.875rem; border-bottom-left-radius: 0.75rem; font-size: 0.75rem; font-weight: 600;">REKOMENDASI</div>
              <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
                <div style="width: 3.5rem; height: 3.5rem; background: white; border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">🚀</div>
                <div style="flex: 1;">
                  <h4 style="margin: 0 0 0.375rem 0; color: #065f46; font-size: 1.15rem; font-weight: 700;">Quick Start Guide</h4>
                  <span style="display: inline-block; background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600;">5 Menit</span>
                </div>
              </div>
              <p style="margin: 0 0 1.25rem 0; font-size: 0.95rem; color: #065f46; line-height: 1.5;">
                Panduan singkat untuk user baru. Dari login hingga kirim email pertama dalam 5 menit.
              </p>
              <div style="display: flex; gap: 0.75rem;">
                <a href="tutorial.php?t=quickstart" class="doc-btn-primary" style="flex: 1; padding: 0.75rem 1rem; background: #10b981; color: white; text-decoration: none; border-radius: 0.625rem; font-weight: 600; font-size: 0.9rem; text-align: center; transition: all 0.2s ease;">Mulai Sekarang →</a>
              </div>
            </div>

            <!-- Main Manual -->
            <div class="doc-card-modern" data-category="modules" data-keywords="manual lengkap komprehensif panduan modul" style="background: white; border-radius: 1rem; padding: 1.75rem; border: 2px solid var(--color-primary-200); transition: all 0.3s ease; cursor: pointer;">
              <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
                <div style="width: 3.5rem; height: 3.5rem; background: linear-gradient(135deg, var(--color-primary-100), var(--color-primary-200)); border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-size: 1.75rem;">📖</div>
                <div style="flex: 1;">
                  <h4 style="margin: 0 0 0.375rem 0; color: var(--color-primary-800); font-size: 1.15rem; font-weight: 700;">Manual Operasional</h4>
                  <span style="display: inline-block; background: var(--color-primary-100); color: var(--color-primary-700); padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600;">16 Modul</span>
                </div>
              </div>
              <p style="margin: 0 0 1.25rem 0; font-size: 0.95rem; color: var(--muted); line-height: 1.5;">
                Panduan lengkap komprehensif dengan tips, best practices, dan troubleshooting untuk seluruh aplikasi.
              </p>
              <div style="display: flex; gap: 0.75rem;">
                <a href="tutorial.php?t=contacts" class="doc-btn-secondary" style="flex: 1; padding: 0.75rem 1rem; background: var(--color-primary-50); color: var(--color-primary-700); text-decoration: none; border-radius: 0.625rem; font-weight: 600; font-size: 0.9rem; text-align: center; border: 2px solid var(--color-primary-200); transition: all 0.2s ease;">Tutorial Interaktif →</a>
                <a href="../MANUAL_OPERASIONAL.md" download class="doc-btn-icon" style="padding: 0.75rem; background: white; color: var(--color-primary-600); text-decoration: none; border-radius: 0.625rem; border: 2px solid var(--color-primary-200); transition: all 0.2s ease; display: flex; align-items: center; justify-content: center;" title="Download PDF">📥</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Module Cards -->
        <div style="margin-bottom: 2rem;">
          <h3 style="color: var(--color-primary-700); font-size: 1.1rem; font-weight: 600; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;" class="category-header" data-category="modules">
            <span style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">📖 Modul Pelatihan</span>
          </h3>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;" class="doc-grid">
            <!-- Module 1 -->
            <div class="doc-card-modern" data-category="modules" data-keywords="kontak contact manajemen crud import export" style="background: white; border-radius: 1rem; padding: 1.5rem; border: 2px solid var(--color-success-200); transition: all 0.3s ease; cursor: pointer;">
              <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.875rem;">
                <div style="width: 3rem; height: 3rem; background: linear-gradient(135deg, #dcfce7, #bbf7d0); border-radius: 0.875rem; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">👥</div>
                <div>
                  <h4 style="margin: 0; color: var(--color-success-700); font-size: 1.05rem; font-weight: 700;">Modul 1: Manajemen Kontak</h4>
                  <span style="font-size: 0.8rem; color: var(--muted);">Add • Edit • Delete • Import</span>
                </div>
              </div>
              <p style="margin: 0 0 1rem 0; font-size: 0.9rem; color: var(--muted); line-height: 1.5;">
                Kelola kontak dengan CRUD operations, bulk import CSV/Excel, search & filter, dan export data.
              </p>
              <div style="display: flex; gap: 0.625rem;">
                <a href="tutorial.php?t=contacts" style="flex: 1; padding: 0.625rem 1rem; background: var(--color-success-50); color: var(--color-success-700); text-decoration: none; border-radius: 0.5rem; font-weight: 600; font-size: 0.85rem; text-align: center; border: 2px solid var(--color-success-200); transition: all 0.2s ease;">Pelajari →</a>
                <a href="../MODUL_1_MANAJEMEN_KONTAK.md" download style="padding: 0.625rem; background: white; color: var(--color-success-600); text-decoration: none; border-radius: 0.5rem; border: 2px solid var(--color-success-200); transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; font-size: 0.9rem;" title="Download">📥</a>
              </div>
            </div>

            <!-- Module 2 -->
            <div class="doc-card-modern" data-category="modules" data-keywords="email kirim compose send template lampiran attachment" style="background: white; border-radius: 1rem; padding: 1.5rem; border: 2px solid var(--color-primary-200); transition: all 0.3s ease; cursor: pointer;">
              <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.875rem;">
                <div style="width: 3rem; height: 3rem; background: linear-gradient(135deg, #dbeafe, #bfdbfe); border-radius: 0.875rem; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">✉️</div>
                <div>
                  <h4 style="margin: 0; color: var(--color-primary-700); font-size: 1.05rem; font-weight: 700;">Modul 2: Compose & Send</h4>
                  <span style="font-size: 0.8rem; color: var(--muted);">Compose • Template • Tracking</span>
                </div>
              </div>
              <p style="margin: 0 0 1rem 0; font-size: 0.9rem; color: var(--muted); line-height: 1.5;">
                Rich text editor, pilih penerima, upload lampiran, AI similarity matching, dan tracking pengiriman.
              </p>
              <div style="display: flex; gap: 0.625rem;">
                <a href="tutorial.php?t=compose" style="flex: 1; padding: 0.625rem 1rem; background: var(--color-primary-50); color: var(--color-primary-700); text-decoration: none; border-radius: 0.5rem; font-weight: 600; font-size: 0.85rem; text-align: center; border: 2px solid var(--color-primary-200); transition: all 0.2s ease;">Pelajari →</a>
                <a href="../MODUL_2_COMPOSE_EMAIL.md" download style="padding: 0.625rem; background: white; color: var(--color-primary-600); text-decoration: none; border-radius: 0.5rem; border: 2px solid var(--color-primary-200); transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; font-size: 0.9rem;" title="Download">📥</a>
              </div>
            </div>

            <!-- AI/Groq Integration -->
            <div class="doc-card-modern" data-category="modules" data-keywords="ai groq artificial intelligence matching assistant" style="background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border-radius: 1rem; padding: 1.5rem; border: 2px solid #a855f7; transition: all 0.3s ease; cursor: pointer;">
              <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.875rem;">
                <div style="width: 3rem; height: 3rem; background: linear-gradient(135deg, #e9d5ff, #d8b4fe); border-radius: 0.875rem; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">🤖</div>
                <div>
                  <h4 style="margin: 0; color: #7e22ce; font-size: 1.05rem; font-weight: 700;">AI & Groq Integration</h4>
                  <span style="font-size: 0.8rem; color: #a855f7; background: #f3e8ff; padding: 0.125rem 0.5rem; border-radius: 1rem;">BARU</span>
                </div>
              </div>
              <p style="margin: 0 0 1rem 0; font-size: 0.9rem; color: #6b21a8; line-height: 1.5;">
                Setup Groq API untuk AI-powered attachment matching dan AI Assistant chat widget.
              </p>
              <div style="display: flex; gap: 0.625rem;">
                <a href="../GROQ_AI_INTEGRATION.md" style="flex: 1; padding: 0.625rem 1rem; background: #f3e8ff; color: #7e22ce; text-decoration: none; border-radius: 0.5rem; font-weight: 600; font-size: 0.85rem; text-align: center; border: 2px solid #d8b4fe; transition: all 0.2s ease;">Setup AI →</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Help & Support Cards -->
        <div>
          <h3 style="color: var(--color-primary-700); font-size: 1.1rem; font-weight: 600; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;" class="category-header" data-category="help">
            <span style="background: linear-gradient(135deg, #f59e0b, #d97706); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">❓ Bantuan & Dukungan</span>
          </h3>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;" class="doc-grid">
            <!-- FAQ -->
            <div class="doc-card-modern" data-category="help" data-keywords="faq frequently asked questions pertanyaan umum" style="background: white; border-radius: 1rem; padding: 1.25rem; border: 2px solid var(--color-warning-200); transition: all 0.3s ease; cursor: pointer;">
              <div style="display: flex; align-items: center; gap: 0.875rem; margin-bottom: 0.75rem;">
                <div style="width: 2.5rem; height: 2.5rem; background: linear-gradient(135deg, #fef3c7, #fde68a); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">❓</div>
                <h4 style="margin: 0; color: var(--color-warning-700); font-size: 1rem; font-weight: 700;">FAQ</h4>
              </div>
              <p style="margin: 0 0 0.875rem 0; font-size: 0.85rem; color: var(--muted); line-height: 1.5;">
                Jawaban untuk pertanyaan yang sering ditanyakan pengguna.
              </p>
              <a href="tutorial.php?t=faq" style="display: block; padding: 0.5rem 1rem; background: var(--color-warning-50); color: var(--color-warning-700); text-decoration: none; border-radius: 0.5rem; font-weight: 600; font-size: 0.8rem; text-align: center; border: 2px solid var(--color-warning-200); transition: all 0.2s ease;">Lihat FAQ →</a>
            </div>

            <!-- Troubleshooting -->
            <div class="doc-card-modern" data-category="help" data-keywords="troubleshoot masalah error solusi problem fix" style="background: white; border-radius: 1rem; padding: 1.25rem; border: 2px solid var(--color-danger-200); transition: all 0.3s ease; cursor: pointer;">
              <div style="display: flex; align-items: center; gap: 0.875rem; margin-bottom: 0.75rem;">
                <div style="width: 2.5rem; height: 2.5rem; background: linear-gradient(135deg, #fee2e2, #fecaca); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">🔧</div>
                <h4 style="margin: 0; color: var(--color-danger-700); font-size: 1rem; font-weight: 700;">Troubleshooting</h4>
              </div>
              <p style="margin: 0 0 0.875rem 0; font-size: 0.85rem; color: var(--muted); line-height: 1.5;">
                Solusi untuk masalah umum, error, dan cara mengatasinya.
              </p>
              <a href="tutorial.php?t=troubleshoot" style="display: block; padding: 0.5rem 1rem; background: var(--color-danger-50); color: var(--color-danger-700); text-decoration: none; border-radius: 0.5rem; font-weight: 600; font-size: 0.8rem; text-align: center; border: 2px solid var(--color-danger-200); transition: all 0.2s ease;">Cari Solusi →</a>
            </div>

            <!-- AI Assistant -->
            <div class="doc-card-modern" data-category="help" data-keywords="ai assistant chat bantuan langsung" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 1rem; padding: 1.25rem; border: 2px solid #3b82f6; transition: all 0.3s ease; cursor: pointer;">
              <div style="display: flex; align-items: center; gap: 0.875rem; margin-bottom: 0.75rem;">
                <div style="width: 2.5rem; height: 2.5rem; background: linear-gradient(135deg, #bfdbfe, #93c5fd); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">💬</div>
                <h4 style="margin: 0; color: #1d4ed8; font-size: 1rem; font-weight: 700;">AI Assistant</h4>
              </div>
              <p style="margin: 0 0 0.875rem 0; font-size: 0.85rem; color: #1e40af; line-height: 1.5;">
                Chat dengan AI Assistant untuk bantuan instan 24/7.
              </p>
              <button onclick="document.getElementById('ai-assistant-toggle').click();" style="display: block; width: 100%; padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 0.5rem; font-weight: 600; font-size: 0.8rem; text-align: center; cursor: pointer; transition: all 0.2s ease;">Chat Sekarang →</button>
            </div>

            <!-- Video Tutorials (Coming Soon) -->
            <div class="doc-card-modern" data-category="help" data-keywords="video tutorial tonton belajar" style="background: #f8fafc; border-radius: 1rem; padding: 1.25rem; border: 2px dashed var(--color-muted); transition: all 0.3s ease; opacity: 0.8;">
              <div style="display: flex; align-items: center; gap: 0.875rem; margin-bottom: 0.75rem;">
                <div style="width: 2.5rem; height: 2.5rem; background: #e2e8f0; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">🎥</div>
                <h4 style="margin: 0; color: var(--muted); font-size: 1rem; font-weight: 700;">Video Tutorials</h4>
              </div>
              <p style="margin: 0 0 0.875rem 0; font-size: 0.85rem; color: var(--muted); line-height: 1.5;">
                Tutorial video step-by-step (Coming Soon).
              </p>
              <span style="display: block; padding: 0.5rem 1rem; background: #e2e8f0; color: var(--muted); border-radius: 0.5rem; font-weight: 600; font-size: 0.8rem; text-align: center;">🔜 Coming Soon</span>
            </div>
          </div>
        </div>
      </div>

      <style>
        .doc-card-modern:hover {
          transform: translateY(-4px);
          box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.15) !important;
        }
        .doc-tab:hover {
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }
        .doc-tab.active {
          box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .doc-btn-primary:hover {
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
          filter: brightness(1.1);
        }
        .doc-btn-secondary:hover {
          background: var(--color-primary-100) !important;
          transform: translateY(-2px);
        }
        .doc-card-modern.hidden {
          display: none !important;
        }
        .category-header.hidden {
          display: none !important;
        }
        @media (max-width: 768px) {
          .docs-section {
            padding: 1.25rem !important;
          }
        }
      </style>

      <script>
        // Filter documentation by category
        function filterDocs(category) {
          const tabs = document.querySelectorAll('.doc-tab');
          const cards = document.querySelectorAll('.doc-card-modern');
          const headers = document.querySelectorAll('.category-header');
          
          // Update active tab
          tabs.forEach(tab => {
            if (tab.dataset.category === category) {
              tab.classList.add('active');
              tab.style.background = 'var(--color-primary-500)';
              tab.style.color = 'white';
              tab.style.borderColor = 'var(--color-primary-500)';
            } else {
              tab.classList.remove('active');
              tab.style.background = 'white';
              tab.style.color = 'var(--color-primary-700)';
              tab.style.borderColor = 'var(--color-primary-200)';
            }
          });
          
          // Filter cards with animation
          cards.forEach((card, index) => {
            const cardCategory = card.dataset.category;
            if (category === 'all' || cardCategory === category) {
              card.classList.remove('hidden');
              card.style.animation = 'fadeInUp 0.4s ease forwards';
              card.style.animationDelay = (index * 0.05) + 's';
            } else {
              card.classList.add('hidden');
            }
          });
          
          // Show/hide category headers
          headers.forEach(header => {
            const headerCategory = header.dataset.category;
            const hasVisibleCards = Array.from(cards).some(card => {
              return (category === 'all' || card.dataset.category === category) && 
                     card.dataset.category === headerCategory;
            });
            if (hasVisibleCards) {
              header.classList.remove('hidden');
            } else {
              header.classList.add('hidden');
            }
          });
        }
        
        // Search functionality
        document.getElementById('docSearch')?.addEventListener('input', function() {
          const query = this.value.toLowerCase().trim();
          const cards = document.querySelectorAll('.doc-card-modern');
          const headers = document.querySelectorAll('.category-header');
          
          if (query === '') {
            // Reset to current category filter
            const activeTab = document.querySelector('.doc-tab.active');
            filterDocs(activeTab?.dataset.category || 'all');
            return;
          }
          
          cards.forEach(card => {
            const keywords = card.dataset.keywords?.toLowerCase() || '';
            const text = card.textContent.toLowerCase();
            
            if (keywords.includes(query) || text.includes(query)) {
              card.classList.remove('hidden');
              card.style.animation = 'fadeInUp 0.3s ease';
            } else {
              card.classList.add('hidden');
            }
          });
          
          // Show all headers when searching
          headers.forEach(header => header.classList.remove('hidden'));
        });
        
        // Add fade in animation
        const style = document.createElement('style');
        style.textContent = `
          @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
          }
        `;
        document.head.appendChild(style);
      </script>

      <!-- Configuration Section -->
      <div class="info-section">
        <h2 style="color: var(--color-primary-700);">⚙️ Konfigurasi Sistem</h2>
        <div class="info-list">
          <div class="info-item" style="border-left: 3px solid var(--color-primary-600);">
            <strong style="color: var(--color-primary-700);">📁 Folder Lampiran:</strong><br>
            <code style="background: var(--color-primary-50); color: var(--color-primary-700);"><?= e(ATTACHMENTS_DIR) ?></code>
            <p class="small" style="color: var(--muted);">Letakkan file lampiran Anda di folder ini atau upload dari menu "Upload Lampiran".</p>
          </div>
          <div class="info-item" style="border-left: 3px solid var(--color-primary-600);">
            <strong style="color: var(--color-primary-700);">📧 Akun Outlook:</strong><br>
            <code style="background: var(--color-primary-50); color: var(--color-primary-700);"><?= e(get_sender_account()) ?></code>
            <p class="small" style="color: var(--muted);">Account yang digunakan untuk mengirim email melalui Outlook COM.</p>
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
    
    <h3 style="color: var(--color-primary-700); margin: 1.5rem 0 1rem 0; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Pilih Avatar</h3>
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
      <small style="color: var(--muted); display: block; margin-top: 0.5rem;">Max 5MB • Format: JPG, PNG, SVG, WebP</small>
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
        '../storage/avatars/' + selectedAvatar;

      fetch('api_update_avatar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ avatar: selectedAvatar })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update avatar display - update the image inside user-avatar div
          const userAvatarDiv = document.querySelector('.user-avatar');
          if (userAvatarDiv) {
            const img = userAvatarDiv.querySelector('img');
            if (img) {
              img.src = avatarPath;
            }
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

<!-- AI Assistant Widget -->
<script src="../assets/js/ai-assistant.js?v=1.0"></script>

