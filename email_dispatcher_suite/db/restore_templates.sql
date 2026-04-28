-- Restore Default Templates
INSERT INTO email_templates (name, description, body, template_type, created_by) 
VALUES 
  ('Template Default - Professional',
   'Template standar profesional',
   '<h2>Subject Email Anda</h2><p>Kepada yang terhormat,</p><p>Berikut adalah informasi yang Anda minta:</p><p>---</p><p>Terima kasih atas perhatian Anda.</p><p>Salam hormat,<br/>Tim</p>',
   'standalone',
   5);

INSERT INTO email_templates (name, description, body, template_type, created_by) 
VALUES 
  ('Template Pengumuman - Resmi',
   'Template untuk pengumuman resmi',
   '<h2>PENGUMUMAN PENTING</h2><p>Kepada semua pihak yang bersangkutan,</p><p><strong>Berikut informasi penting yang perlu Anda ketahui:</strong></p><ul><li>Poin pertama</li><li>Poin kedua</li><li>Poin ketiga</li></ul><p>Perhatikan baik-baik informasi di atas.</p><p>Demikian untuk diketahui.</p>',
   'standalone',
   5);

INSERT INTO email_templates (name, description, body, template_type, created_by) 
VALUES 
  ('Template Laporan - Bulanan',
   'Template untuk laporan bulanan',
   '<h2>LAPORAN BULAN [BULAN/TAHUN]</h2><p>Dengan hormat,</p><p>Berikut adalah laporan untuk periode [PERIODE]:</p><table style="width:100%; border-collapse: collapse; border: 1px solid #ccc;"><tr style="background: #f0f0f0;"><th style="border: 1px solid #ccc; padding: 8px;">Item</th><th style="border: 1px solid #ccc; padding: 8px;">Total</th></tr><tr><td style="border: 1px solid #ccc; padding: 8px;">Keterangan</td><td style="border: 1px solid #ccc; padding: 8px;">0</td></tr></table><p><strong>Total Keseluruhan: 0</strong></p><p>Terima kasih.</p>',
   'standalone',
   5);

-- Verify restoration
SELECT COUNT(*) as template_count FROM email_templates;
SELECT id, name, description FROM email_templates ORDER BY id;
