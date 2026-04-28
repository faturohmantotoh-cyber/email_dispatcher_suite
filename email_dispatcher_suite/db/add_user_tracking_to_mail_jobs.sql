-- Add user_id tracking to mail_jobs for proper multi-user support
-- This ensures each email job is linked to the user who created it

-- Step 1: Add created_by column to mail_jobs (if not exists)
-- Check first
SHOW COLUMNS FROM mail_jobs WHERE Field = 'created_by';

-- Step 2: Update existing rows to use first admin user
-- Find first admin user and use that ID
UPDATE mail_jobs 
SET created_by = (SELECT MIN(id) FROM users WHERE role = 'admin')
WHERE created_by IS NULL;

-- Step 3: Verify all rows have a created_by value
SELECT 'Checking created_by after update:' as step;
SELECT COUNT(*) as rows_with_created_by FROM mail_jobs WHERE created_by IS NOT NULL;
SELECT COUNT(*) as rows_null FROM mail_jobs WHERE created_by IS NULL;

-- Step 4: If constraint doesn't exist, add it
-- ALTER TABLE mail_jobs
-- ADD CONSTRAINT fk_mail_jobs_created_by 
-- FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Step 5: Add indexes for better query performance (IF NOT EXISTS)
ALTER TABLE mail_jobs
ADD INDEX idx_created_by (created_by),
ADD INDEX idx_created_by_status (created_by, status),
ADD INDEX idx_created_by_created_at (created_by, created_at DESC);

-- Verify 
SELECT 'Mail Jobs Updated' as status;
SELECT created_by, COUNT(*) as job_count FROM mail_jobs GROUP BY created_by;
