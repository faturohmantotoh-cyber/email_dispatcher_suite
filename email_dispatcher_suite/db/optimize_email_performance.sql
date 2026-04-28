-- Performance Optimization: Add Indexes & Cleanup
-- This script improves email job processing performance

-- Create indexes using a safe method (ignore if they already exist)
-- For mail_jobs table

-- Check current indexes before adding
SELECT 'Current mail_jobs Indexes:' as step;
SHOW INDEX FROM mail_jobs WHERE Key_name NOT IN ('PRIMARY');

-- Try to add indexes (will error if exists, but that's OK - continue anyway)
/* Indexes for mail_jobs */
ALTER TABLE mail_jobs ADD INDEX idx_status_created (status, created_at);

/* Indexes for mail_job_items */
ALTER TABLE mail_job_items ADD INDEX idx_mail_job_status (mail_job_id, status);
ALTER TABLE mail_job_items ADD INDEX idx_recipient_email (recipient_email);
ALTER TABLE mail_job_items ADD INDEX idx_mail_job_recipient (mail_job_id, recipient_email);

-- Optional: Clean up very old completed jobs (older than 90 days)
-- Uncomment the lines below to enable automatic cleanup:

-- DELETE ji FROM mail_job_items ji
-- INNER JOIN mail_jobs j ON j.id = ji.mail_job_id
-- WHERE j.created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
--   AND j.status IN ('completed', 'failed');

-- DELETE FROM mail_jobs 
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
--   AND status IN ('completed', 'failed');

-- Create permanent cleanup procedure (if not exists)
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS cleanup_old_jobs(IN days_old INT)
BEGIN
    DECLARE affected_jobs INT DEFAULT 0;
    DECLARE affected_items INT DEFAULT 0;
    
    -- Get count of items to delete
    SELECT COUNT(*) INTO affected_items
    FROM mail_job_items ji
    INNER JOIN mail_jobs j ON j.id = ji.mail_job_id
    WHERE j.created_at < DATE_SUB(NOW(), INTERVAL days_old DAY)
      AND j.status IN ('completed', 'failed');
    
    -- Delete old items
    DELETE ji FROM mail_job_items ji
    INNER JOIN mail_jobs j ON j.id = ji.mail_job_id
    WHERE j.created_at < DATE_SUB(NOW(), INTERVAL days_old DAY)
      AND j.status IN ('completed', 'failed');
    
    -- Get count of jobs to delete
    SELECT COUNT(*) INTO affected_jobs
    FROM mail_jobs
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_old DAY)
      AND status IN ('completed', 'failed');
    
    -- Delete old jobs
    DELETE FROM mail_jobs
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_old DAY)
      AND status IN ('completed', 'failed');
    
    -- Return results
    SELECT affected_jobs AS deleted_jobs, affected_items AS deleted_items;
END$$

DELIMITER ;

-- Usage: CALL cleanup_old_jobs(90);  -- Delete jobs older than 90 days
-- Or: CALL cleanup_old_jobs(30);  -- Delete jobs older than 30 days

SHOW INDEX FROM mail_jobs;
SHOW INDEX FROM mail_job_items;
