-- Email Templates Database Migration
-- Allows admin to create templates linked to groups/group_orders

CREATE TABLE IF NOT EXISTS email_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Template name',
  description TEXT NULL COMMENT 'Template description',
  body MEDIUMTEXT NOT NULL COMMENT 'Email body template (HTML)',
  template_type ENUM('standalone', 'group', 'group_order') DEFAULT 'standalone' COMMENT 'Template type',
  created_by INT NULL COMMENT 'User ID who created (NULL if creator deleted)',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  INDEX idx_type (template_type),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Association table: templates to groups
CREATE TABLE IF NOT EXISTS template_group_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  group_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_template_group (template_id, group_id),
  FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE CASCADE,
  FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
  INDEX idx_group_id (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Association table: templates to group_orders
CREATE TABLE IF NOT EXISTS template_group_order_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  group_order_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_template_grouporder (template_id, group_order_id),
  FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE CASCADE,
  FOREIGN KEY (group_order_id) REFERENCES group_orders(id) ON DELETE CASCADE,
  INDEX idx_group_order_id (group_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Insert sample templates
INSERT INTO email_templates (name, description, body, template_type, created_by) 
SELECT 
  'Template Default - Professional',
  'Template standar profesional',
  '<h2>Subject Email Anda</h2>
<p>Kepada yang terhormat,</p>
<p>Berikut adalah informasi yang Anda minta:</p>
<p>---</p>
<p>Terima kasih atas perhatian Anda.</p>
<p>Salam hormat,<br>Tim</p>',
  'standalone',
  id
FROM users WHERE role = 'admin' LIMIT 1;

INSERT INTO email_templates (name, description, body, template_type, created_by) 
SELECT 
  'Template Pengumuman - Resmi',
  'Template untuk pengumuman resmi',
  '<h2>📢 PENGUMUMAN PENTING</h2>
<p>Kepada semua pihak yang bersangkutan,</p>
<p><strong>Berikut informasi penting yang perlu Anda ketahui:</strong></p>
<ul>
  <li>Poin pertama</li>
  <li>Poin kedua</li>
  <li>Poin ketiga</li>
</ul>
<p>Perhatikan baik-baik informasi di atas.</p>
<p>Demikian untuk diketahui.</p>',
  'standalone',
  id
FROM users WHERE role = 'admin' LIMIT 1;

INSERT INTO email_templates (name, description, body, template_type, created_by) 
SELECT 
  'Template Laporan - Bulanan',
  'Template untuk laporan bulanan',
  '<h2>LAPORAN BULAN [BULAN/TAHUN]</h2>
<p>Dengan hormat,</p>
<p>Berikut adalah laporan untuk periode [PERIODE]:</p>
<table style="width:100%; border-collapse: collapse; border: 1px solid #ccc;">
  <tr style="background: #f0f0f0;">
    <th style="border: 1px solid #ccc; padding: 8px;">Item</th>
    <th style="border: 1px solid #ccc; padding: 8px;">Total</th>
  </tr>
  <tr>
    <td style="border: 1px solid #ccc; padding: 8px;">Keterangan</td>
    <td style="border: 1px solid #ccc; padding: 8px;">0</td>
  </tr>
</table>
<p><strong>Total Keseluruhan: 0</strong></p>
<p>Terima kasih.</p>',
  'standalone',
  id
FROM users WHERE role = 'admin' LIMIT 1;
