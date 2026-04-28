-- Update existing mail_jobs to assign to first admin user
-- This assigns all existing jobs to the first admin found in the system
UPDATE mail_jobs 
SET created_by = (
    SELECT MIN(id) FROM users WHERE role = 'admin'
)
WHERE created_by IS NULL;

-- Verify the update
SELECT 
    COUNT(*) as total_jobs,
    COUNT(CASE WHEN created_by IS NOT NULL THEN 1 END) as jobs_with_user,
    COUNT(CASE WHEN created_by IS NULL THEN 1 END) as jobs_without_user
FROM mail_jobs;
