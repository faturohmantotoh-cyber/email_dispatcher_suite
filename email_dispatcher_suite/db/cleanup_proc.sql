-- Create the cleanup_old_jobs stored procedure
DELIMITER //

DROP PROCEDURE IF EXISTS cleanup_old_jobs //

CREATE PROCEDURE cleanup_old_jobs(IN days_old INT)
BEGIN
    -- Delete mail_job_items for old completed/failed jobs
    DELETE FROM mail_job_items 
    WHERE mail_job_id IN (
        SELECT id FROM mail_jobs 
        WHERE (status = 'completed' OR status = 'failed')
        AND created_at < DATE_SUB(NOW(), INTERVAL days_old DAY)
    );
    
    -- Delete old completed/failed mail_jobs
    DELETE FROM mail_jobs 
    WHERE (status = 'completed' OR status = 'failed')
    AND created_at < DATE_SUB(NOW(), INTERVAL days_old DAY);
    
    -- Return success message
    SELECT 'Cleanup completed successfully' as message;
END //

DELIMITER ;
