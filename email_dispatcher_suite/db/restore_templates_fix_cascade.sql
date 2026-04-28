-- Restore Templates After User Deletion
-- This script fixes the ON DELETE CASCADE issue and restores default templates

-- Step 1: Drop the old foreign key constraint
ALTER TABLE email_templates 
DROP FOREIGN KEY email_templates_ibfk_1;

-- Step 2: Add new constraint that preserves templates when user is deleted
ALTER TABLE email_templates 
ADD CONSTRAINT fk_email_templates_created_by 
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Step 3: Get any remaining admin user ID (for created_by field)
-- If no admin exists, we'll use NULL for now
SET @admin_id = (SELECT id FROM users WHERE role = 'admin' LIMIT 1);

-- If still no admin, use id 1 if it exists
IF @admin_id IS NULL THEN
  SET @admin_id = (SELECT id FROM users LIMIT 1);
END IF;

-- Step 4: Restore default templates (if they don't exist)
-- These templates will be preserved even if the creating user is deleted
INSERT INTO email_templates (name, description, body, template_type, created_by) 
VALUES 
  ('Template Default - Professional',
   'Template standar profesional',
   '<h2>Subject Email Anda</h2><p>Kepada yang terhormat,</p><p>Berikut adalah informasi yang Anda minta:</p><p>---</p><p>Terima kasih atas perhatian Anda.</p><p>Salam hormat,<br>Tim</p>',
   'standalone',
   @admin_id)
ON DUPLICATE KEY UPDATE created_by = @admin_id;

INSERT INTO email_templates (name, description, body, template_type, created_by) 
VALUES 
  ('Template Pengumuman - Resmi',
   'Template untuk pengumuman resmi',
   '<h2>📢 PENGUMUMAN PENTING</h2><p>Kepada semua pihak yang bersangkutan,</p><p><strong>Berikut informasi penting yang perlu Anda ketahui:</strong></p><ul><li>Poin pertama</li><li>Poin kedua</li><li>Poin ketiga</li></ul><p>Perhatikan baik-baik informasi di atas.</p><p>Demikian untuk diketahui.</p>',
   'standalone',
   @admin_id)
ON DUPLICATE KEY UPDATE created_by = @admin_id;

INSERT INTO email_templates (name, description, body, template_type, created_by) 
VALUES 
  ('Template Laporan - Bulanan',
   'Template untuk laporan bulanan',
   '<h2>LAPORAN BULAN [BULAN/TAHUN]</h2><p>Dengan hormat,</p><p>Berikut adalah laporan untuk periode [PERIODE]:</p><table style="width:100%; border-collapse: collapse; border: 1px solid #ccc;"><tr style="background: #f0f0f0;"><th style="border: 1px solid #ccc; padding: 8px;">Item</th><th style="border: 1px solid #ccc; padding: 8px;">Total</th></tr><tr><td style="border: 1px solid #ccc; padding: 8px;">Keterangan</td><td style="border: 1px solid #ccc; padding: 8px;">0</td></tr></table><p><strong>Total Keseluruhan: 0</strong></p><p>Terima kasih.</p>',
   'standalone',
   @admin_id)
ON DUPLICATE KEY UPDATE created_by = @admin_id;

-- Step 5: Verify templates were restored
SELECT 'Templates after restoration:' as status;
SELECT id, name, created_by FROM email_templates ORDER BY id;

-- Step 6: Show foreign key status
SELECT 'Foreign Key Constraint Status:' as status;
SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, DELETE_RULE 
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_NAME = 'email_templates' AND COLUMN_NAME = 'created_by';
