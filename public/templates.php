<?php
/**
 * templates.php - Email templates management page
 * Admin only - create, edit, delete email templates
 * Link templates to groups and group orders
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';
require_once __DIR__ . '/../lib/security.php';

ensure_dirs();
$pdo = DB::conn();
SecurityManager::init($pdo);

// Check authentication and admin role
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

if (!hasRole('admin')) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = '';
$csrf = SecurityManager::generateCSRFToken();

// Fetch all templates
$templateStmt = $pdo->query("
    SELECT id, name, description, template_type, created_at 
    FROM email_templates 
    ORDER BY created_at DESC
");
$templates = $templateStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all groups
$groupStmt = $pdo->query("
    SELECT `id`, `name`, COUNT(gm.contact_id) as member_count
    FROM `groups` g
    LEFT JOIN `group_members` gm ON gm.`group_id` = g.`id`
    GROUP BY g.`id`, g.`name`
    ORDER BY g.`name`
");
$groups = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all group orders
$groupOrderStmt = $pdo->query("
    SELECT `id`, `name`
    FROM `group_orders`
    ORDER BY `name`
");
$groupOrders = $groupOrderStmt->fetchAll(PDO::FETCH_ASSOC);

// Get template-group links
$templateGroupLinks = [];
$linkStmt = $pdo->query("
    SELECT template_id, group_id 
    FROM template_group_links
");
while ($row = $linkStmt->fetch(PDO::FETCH_ASSOC)) {
    $key = 'group_' . $row['group_id'];
    $templateGroupLinks[$key] = $row['template_id'];
}

// Get template-group order links
$templateGroupOrderLinks = [];
$linkStmt = $pdo->query("
    SELECT template_id, group_order_id 
    FROM template_group_order_links
");
while ($row = $linkStmt->fetch(PDO::FETCH_ASSOC)) {
    $key = 'grouporder_' . $row['group_order_id'];
    $templateGroupOrderLinks[$key] = $row['template_id'];
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Template Email</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/custom.css?v=3.0">
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.snow.css?v=2.0" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.js?v=2.0"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; background: #f7f7fb; color: #222; font-size: 15px; letter-spacing: -0.01em; }
header { background: #0d6efd; color: #fff; padding: 16px; }
main { padding: 20px; max-width: 1200px; margin: 0 auto; }
.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 16px; }
.card h3 { margin: 0; padding: 12px 16px; border-bottom: 1px solid #e5e7eb; }
.card .body { padding: 16px; }
.btn { display: inline-block; background: #0d6efd; color: #fff; padding: 8px 12px; border-radius: 6px; text-decoration: none; border: 0; cursor: pointer; }
.btn.secondary { background: #6b7280; }
.btn.danger { background: #dc2626; }
.btn.success { background: #10b981; }
.btn-sm { padding: 6px 10px; font-size: 12px; }

.alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
.alert.success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
.alert.error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }

label { display: block; margin-top: 8px; font-weight: 500; }
input, select { padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; width: 100%; box-sizing: border-box; margin-top: 4px; }

.ql-container { height: 300px; }
.ql-editor { min-height: 300px; }

.template-list { margin-top: 20px; }
.template-item { background: #f9fafb; padding: 12px; border-radius: 6px; margin-bottom: 8px; border-left: 3px solid #0d6efd; }
.template-item h4 { margin: 0 0 4px 0; color: #0d6efd; }
.template-item p { margin: 0; font-size: 12px; color: #666; }

.tab-content { display: none; }
.tab-content.active { display: block; }

.tabs { display: flex; gap: 0; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px; }
.tabs button { background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; padding: 12px 20px; font-weight: 500; color: #6b7280; transition: all .15s; }
.tabs button.active { color: #0d6efd; border-bottom-color: #0d6efd; }
.tabs button:hover { background: #f3f4f6; }

.link-section { background: #f9fafb; padding: 12px; border-radius: 6px; margin-top: 12px; }
.link-section h5 { margin-top: 0; font-size: 14px; }

.template-accordion-content { animation: slideDown 0.2s ease-out; }
@keyframes slideDown {
  from {
    opacity: 0;
    max-height: 0;
  }
  to {
    opacity: 1;
    max-height: 1000px;
  }
}

input[type="checkbox"] { width: auto; margin: 0; }
</style>
</head>
<body>
<header>
  <h2 style="margin:0 0 8px 0;">📧 Template Email</h2>
  <div style="font-size: 13px; margin-top: 12px;">
    <a href="index.php" style="color: #fff; text-decoration: none; margin-right: 16px;">⟵ Dashboard</a>
    <a href="settings.php" style="color: #fff; text-decoration: none;">⚙️ Settings</a>
  </div>
</header>

<main>
  <?php if ($message): ?>
  <div class="alert <?= $messageType ?>">
    <?= e($message) ?>
  </div>
  <?php endif; ?>

  <!-- Tabs untuk CRUD -->
  <div class="tabs">
    <button class="tab-btn active" data-tab="list" onclick="switchTab('list', this)">📋 Daftar Template</button>
    <button class="tab-btn" data-tab="create" onclick="switchTab('create', this)">➕ Buat Template</button>
    <button class="tab-btn" data-tab="links" onclick="switchTab('links', this)">🔗 Konfigurasi Link</button>
  </div>

  <!-- ===== TAB 1: LIST TEMPLATES ===== -->
  <div id="tab-list" class="tab-content active">
    <div class="card">
      <h3>📋 Daftar Template Email</h3>
      <div class="body">
        <?php if (empty($templates)): ?>
        <p style="color: #999;">Belum ada template. <a href="#" onclick="switchTab('create')">Buat yang pertama</a></p>
        <?php else: ?>
        <div class="template-list">
          <?php foreach ($templates as $tpl): ?>
          <div class="template-item">
            <div style="display: flex; justify-content: space-between; align-items: start;">
              <div style="flex: 1;">
                <h4><?= e($tpl['name']) ?></h4>
                <p><?= e($tpl['description'] ?? 'Tanpa deskripsi') ?></p>
                <p style="font-size: 11px; color: #999; margin-top: 4px;">
                  Tipe: <strong><?= ucfirst($tpl['template_type']) ?></strong> • 
                  Dibuat: <strong><?= date('d M Y H:i', strtotime($tpl['created_at'])) ?></strong>
                </p>
              </div>
              <div style="margin-left: 12px;">
                <button class="btn btn-sm" onclick="editTemplate(<?= $tpl['id'] ?>)">✏️ Edit</button>
                <button class="btn btn-sm danger" onclick="deleteTemplate(<?= $tpl['id'] ?>, '<?= e($tpl['name']) ?>')">🗑️ Hapus</button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ===== TAB 2: CREATE/EDIT TEMPLATE ===== -->
  <div id="tab-create" class="tab-content">
    <div class="card">
      <h3>➕ Buat Template Email Baru</h3>
      <div class="body">
        <form id="templateForm" onsubmit="return saveTemplate(event)" method="post">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" id="templateId" name="id" value="">

          <label>Nama Template <span style="color: #dc2626;">*</span></label>
          <input type="text" id="templateName" name="name" placeholder="Contoh: Template Professional" required>
          <p style="font-size: 12px; color: #666; margin-top: 4px;">Nama harus unik dan mudah diingat</p>

          <label style="margin-top: 12px;">Deskripsi</label>
          <textarea id="templateDesc" name="description" placeholder="Deskripsi singkat template ini" style="height: 60px; resize: vertical;"></textarea>

          <label style="margin-top: 12px;">Tipe Template <span style="color: #dc2626;">*</span></label>
          <select id="templateType" name="template_type" required>
            <option value="standalone">Standalone (tidak terikat grup)</option>
            <option value="group">Group (terikat ke group convensional)</option>
            <option value="group_order">Group Order (terikat ke group order)</option>
          </select>

          <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 10px; margin-top: 12px; font-size: 12px; color: #065f46;">
            <strong>💡 Tips Subject Email dengan Delivery Date:</strong>
            <ul style="margin: 6px 0 0 18px; padding: 0;">
              <li><strong>Untuk template Group/Group Order</strong>: Saat user compose email dan memilih group/group order, sistem akan otomatis menambahkan:
                <br/><code style="background: #fff; padding: 2px 4px; border-radius: 3px;">tanggal delivery</code> dan <code style="background: #fff; padding: 2px 4px; border-radius: 3px;">(nama grup)</code>
              </li>
              <li>Contoh: Jika subject template = <code style="background: #fff; padding: 2px 4px; border-radius: 3px;">Summary Order ADM KAP Delivery</code>
                <br/>Final subject akan = <code style="background: #fff; padding: 2px 4px; border-radius: 3px;">Summary Order ADM KAP Delivery Sabtu, 07 Maret 2026 (PL5_CKD)</code>
              </li>
              <li>User bisa memilih tanggal delivery di form compose email</li>
            </ul>
          </div>

          <label style="margin-top: 12px;">Isi Email <span style="color: #dc2626;">*</span></label>
          <div id="quillEditor" style="margin-top: 4px;"></div>
          <input type="hidden" id="templateBody" name="body" required>

          <div style="margin-top: 16px;">
            <button type="submit" class="btn success">💾 Simpan Template</button>
            <button type="button" class="btn secondary" onclick="resetTemplateForm()">🔄 Reset</button>
            <button type="button" class="btn secondary" onclick="switchTab('list')">❌ Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===== TAB 3: LINK TEMPLATES TO GROUPS ===== -->
  <div id="tab-links" class="tab-content">
    <div class="card">
      <h3>🔗 Konfigurasi Template ke Group/Group Order</h3>
      <div class="body">
        <p style="color: #666; font-size: 13px; margin-bottom: 16px;">
          Pilih template di bawah, kemudian tentukan groups atau group orders mana yang akan menggunakan template tersebut.
        </p>

        <div style="display: grid; gap: 12px;">
          <?php foreach ($templates as $tpl): ?>
          <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden;">
            <!-- Template Header -->
            <div style="background: #f3f4f6; padding: 12px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                 onclick="toggleTemplateAccordion(<?= $tpl['id'] ?>)">
              <div>
                <strong style="font-size: 14px;"><?= e($tpl['name']) ?></strong>
                <p style="margin: 4px 0 0; font-size: 12px; color: #666;">
                  <?= e(substr($tpl['description'], 0, 80)) ?><?= strlen($tpl['description']) > 80 ? '...' : '' ?>
                </p>
              </div>
              <span id="arrow-<?= $tpl['id'] ?>" style="font-size: 16px; transition: transform 0.2s;">▼</span>
            </div>

            <!-- Template Content (Accordion) -->
            <div id="content-<?= $tpl['id'] ?>" class="template-accordion-content" style="display: none; padding: 12px; background: #fff;">
              
              <!-- Groups Section -->
              <div style="margin-bottom: 16px;">
                <h5 style="margin: 0 0 8px 0; font-size: 13px; color: #374151;">📌 Link ke Groups</h5>
                <div style="display: grid; gap: 6px; max-height: 250px; overflow-y: auto;">
                  <?php if (empty($groups)): ?>
                    <p style="color: #999; font-size: 12px;">Belum ada group.</p>
                  <?php else: ?>
                    <?php foreach ($groups as $group):
                      $linkKey = 'group_' . $group['id'];
                      $linkedTemplateId = $templateGroupLinks[$linkKey] ?? null;
                      $isLinked = ($linkedTemplateId == $tpl['id']);
                    ?>
                    <label style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; padding: 8px; border-radius: 4px; hover: {background: #f0f0f0;}">
                      <input type="checkbox" 
                             value="<?= $tpl['id'] ?>" 
                             <?= $isLinked ? 'checked' : '' ?>
                             onchange="linkGroupTemplate(this, <?= $group['id'] ?>)"
                             style="cursor: pointer;">
                      <span style="font-size: 13px;">
                        <?= e($group['name']) ?> <span style="color: #999;">(<?= $group['member_count'] ?> members)</span>
                      </span>
                    </label>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Group Orders Section -->
              <div>
                <h5 style="margin: 0 0 8px 0; font-size: 13px; color: #374151;">📌 Link ke Group Orders</h5>
                <div style="display: grid; gap: 6px; max-height: 250px; overflow-y: auto;">
                  <?php if (empty($groupOrders)): ?>
                    <p style="color: #999; font-size: 12px;">Belum ada group order.</p>
                  <?php else: ?>
                    <?php foreach ($groupOrders as $groupOrder):
                      $linkKey = 'grouporder_' . $groupOrder['id'];
                      $linkedTemplateId = $templateGroupOrderLinks[$linkKey] ?? null;
                      $isLinked = ($linkedTemplateId == $tpl['id']);
                    ?>
                    <label style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; padding: 8px; border-radius: 4px; hover: {background: #f0f0f0;}">
                      <input type="checkbox" 
                             value="<?= $tpl['id'] ?>" 
                             <?= $isLinked ? 'checked' : '' ?>
                             onchange="linkGroupOrderTemplate(this, <?= $groupOrder['id'] ?>)"
                             style="cursor: pointer;">
                      <span style="font-size: 13px;">
                        <?= e($groupOrder['name']) ?>
                      </span>
                    </label>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>

            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if (empty($templates)): ?>
        <p style="text-align: center; color: #999; padding: 20px;">Belum ada template. Buat template terlebih dahulu.</p>
        <?php endif; ?>

      </div>
    </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize Quill editor
const quill = new Quill('#quillEditor', {
  theme: 'snow',
  modules: {
    toolbar: [
      [{ 'header': [1, 2, 3, false] }],
      ['bold', 'italic', 'underline', 'strike'],
      ['blockquote', 'code-block'],
      [{ 'list': 'ordered'}, { 'list': 'bullet' }],
      [{ 'color': [] }, { 'background': [] }],
      ['link', 'image'],
      ['clean']
    ]
  }
});

// Switch tabs
function switchTab(tabName, eventTarget = null) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
  
  document.getElementById('tab-' + tabName).classList.add('active');
  
  // Set active button - either from event target or find by data attribute
  if (eventTarget) {
    eventTarget.classList.add('active');
  } else {
    const btn = document.querySelector('[data-tab="' + tabName + '"]');
    if (btn) btn.classList.add('active');
  }
}

// Save template
function saveTemplate(event) {
  event.preventDefault();
  
  const name = document.getElementById('templateName').value.trim();
  const body = quill.root.innerHTML;
  const templateId = document.getElementById('templateId').value;
  const templateType = document.getElementById('templateType').value;
  
  console.log('💾 Saving template:', {
    id: templateId,
    name: name,
    template_type: templateType,
    bodyLength: body.length
  });
  
  if (!name || !body) {
    Swal.fire('Error', 'Nama dan isi template harus diisi', 'error');
    return false;
  }
  
  document.getElementById('templateBody').value = body;
  
  const formData = new FormData(document.getElementById('templateForm'));
  const action = templateId ? 'update' : 'create';
  
  console.log('📨 FormData keys:', Array.from(formData.keys()));
  console.log('📨 FormData template_type value:', formData.get('template_type'));
  
  formData.append('action', action);
  
  fetch('api_templates.php', {
    method: 'POST',
    body: formData
  })
  .then(r => {
    if (!r.ok) throw new Error('HTTP Error: ' + r.status);
    return r.json();
  })
  .then(data => {
    console.log('✅ Save response:', data);
    if (data && data.ok) {
      Swal.fire('Berhasil', 'Template tersimpan', 'success').then(() => {
        location.reload();
      });
    } else {
      Swal.fire('Error', data?.error || 'Gagal menyimpan template', 'error');
    }
  })
  .catch(err => {
    console.error('Save template error:', err);
    Swal.fire('Error', 'Gagal menyimpan: ' + err.message, 'error');
  });
  
  return false;
}

// Reset form
function resetTemplateForm() {
  document.getElementById('templateForm').reset();
  document.getElementById('templateId').value = '';
  quill.setContents([]);
}

// Edit template
function editTemplate(templateId) {
  console.log('🚀 editTemplate called with ID:', templateId);
  
  fetch('api_templates.php?action=get&id=' + templateId)
    .then(r => {
      if (!r.ok) throw new Error('HTTP Error: ' + r.status);
      return r.json();
    })
    .then(data => {
      console.log('📥 API Response:', data);
      
      if (!data || !data.ok) {
        Swal.fire('Error', data?.error || 'Gagal memuat template', 'error');
        return;
      }
      
      const tpl = data.data;
      console.log('📄 Template Data:', tpl);
      
      if (!tpl || !tpl.id) {
        Swal.fire('Error', 'Data template tidak lengkap', 'error');
        return;
      }
      
      // Set form values
      console.log('🔧 Setting form values...');
      document.getElementById('templateId').value = tpl.id || '';
      document.getElementById('templateName').value = tpl.name || '';
      document.getElementById('templateDesc').value = tpl.description || '';
      
      const typeSelect = document.getElementById('templateType');
      console.log('📝 Setting template_type to:', tpl.template_type, 'Select element:', typeSelect);
      typeSelect.value = tpl.template_type || 'standalone';
      console.log('✅ After setting - Select value:', typeSelect.value);
      
      // Set Quill content
      if (tpl.body) {
        quill.root.innerHTML = tpl.body;
      } else {
        quill.setContents([]);
      }
      
      console.log('🔄 Switching to create tab...');
      switchTab('create');
      console.log('✨ Done loading template');
    })
    .catch(err => {
      console.error('Edit template error:', err);
      Swal.fire('Error', 'Gagal memuat template: ' + err.message, 'error');
    });
}

// Delete template
async function deleteTemplate(templateId, name) {
  const result = await Swal.fire({
    title: 'Hapus Template?',
    html: '<p style="color: #666; font-size: 14px;">Apakah Anda yakin ingin menghapus template <strong>"' + name + '"</strong>?</p><p style="color: #dc3545; font-weight: bold;"> Tindakan ini TIDAK DAPAT DIBATALKAN!</p>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Batal',
    cancelButtonColor: '#6b7280',
    allowOutsideClick: false,
    allowEscapeKey: false
  });
  
  if (result.isConfirmed) {
    // Prompt for admin password
    const { value: password } = await Swal.fire({
      title: 'Konfirmasi Password Administrator',
      input: 'password',
      inputLabel: 'Masukkan password Anda untuk melanjutkan:',
      inputPlaceholder: 'Password',
      inputAttributes: {
        maxlength: 50,
        autocapitalize: 'off',
        autocorrect: 'off'
      },
      showCancelButton: true,
      confirmButtonText: 'Konfirmasi',
      confirmButtonColor: '#dc2626',
      cancelButtonText: 'Batal',
      cancelButtonColor: '#6b7280',
      allowOutsideClick: false,
      allowEscapeKey: false,
      inputValidator: (value) => {
        if (!value) {
          return 'Password wajib diisi!'
        }
      }
    });
    
    if (password) {
      const formData = new FormData();
      formData.append('action', 'delete');
      formData.append('id', templateId);
      formData.append('csrf_token', '<?= e($csrf) ?>');
      formData.append('admin_password', password);
      
      fetch('api_templates.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.ok) {
          Swal.fire('Terhapus', 'Template berhasil dihapus', 'success');
          location.reload();
        } else {
          Swal.fire('Error', data?.error || 'Gagal menghapus template', 'error');
        }
      })
      .catch(err => {
        console.error('Delete template error:', err);
        Swal.fire('Error', 'Gagal menghapus: ' + err.message, 'error');
      });
    }
  }
}

// Toggle template accordion
function toggleTemplateAccordion(templateId) {
  const content = document.getElementById('content-' + templateId);
  const arrow = document.getElementById('arrow-' + templateId);
  
  if (content.style.display === 'none') {
    content.style.display = 'block';
    arrow.style.transform = 'rotate(180deg)';
  } else {
    content.style.display = 'none';
    arrow.style.transform = 'rotate(0deg)';
  }
}

// Link template to group (checkbox based)
function linkGroupTemplate(checkbox, groupId) {
  const templateId = checkbox.value;
  const isChecked = checkbox.checked;
  
  const formData = new FormData();
  formData.append('action', 'link_group');
  formData.append('template_id', isChecked ? templateId : 0);
  formData.append('group_id', groupId);
  formData.append('csrf_token', '<?= e($csrf) ?>');
  
  fetch('api_templates.php', {
    method: 'POST',
    body: formData
  })
  .then(r => {
    if (!r.ok) throw new Error('HTTP Error: ' + r.status);
    return r.json();
  })
  .then(data => {
    if (data && data.ok) {
      console.log('✓ Template link updated for group:', groupId);
      Swal.fire('Berhasil', isChecked ? 'Template tertaut' : 'Template tidak tertaut', 'success');
    } else {
      checkbox.checked = !isChecked;
      Swal.fire('Error', data?.error || 'Gagal mengubah link template', 'error');
    }
  })
  .catch(err => {
    console.error('Link template error:', err);
    checkbox.checked = !isChecked;
    Swal.fire('Error', 'Gagal mengupdate link: ' + err.message, 'error');
  });
}

// Link template to group order (checkbox based)
function linkGroupOrderTemplate(checkbox, groupOrderId) {
  const templateId = checkbox.value;
  const isChecked = checkbox.checked;
  
  const formData = new FormData();
  formData.append('action', 'link_group_order');
  formData.append('template_id', isChecked ? templateId : 0);
  formData.append('group_order_id', groupOrderId);
  formData.append('csrf_token', '<?= e($csrf) ?>');
  
  fetch('api_templates.php', {
    method: 'POST',
    body: formData
  })
  .then(r => {
    if (!r.ok) throw new Error('HTTP Error: ' + r.status);
    return r.json();
  })
  .then(data => {
    if (data && data.ok) {
      console.log('✓ Template link updated for group order:', groupOrderId);
      Swal.fire('Berhasil', isChecked ? 'Template tertaut' : 'Template tidak tertaut', 'success');
    } else {
      checkbox.checked = !isChecked;
      Swal.fire('Error', data?.error || 'Gagal mengubah link template', 'error');
    }
  })
  .catch(err => {
    console.error('Link template error:', err);
    checkbox.checked = !isChecked;
    Swal.fire('Error', 'Gagal mengupdate link: ' + err.message, 'error');
  });
}
</script>

<!-- AI Assistant Widget -->
<script src="../assets/js/ai-assistant.js?v=1.0"></script>

</body>
</html>
