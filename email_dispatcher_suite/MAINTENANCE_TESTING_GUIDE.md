# Maintenance Button - Testing & Troubleshooting Guide

## Quick Test Checklist

### Pre-Test Verification
- [ ] Stored procedure exists: `SHOW PROCEDURE STATUS WHERE Name='cleanup_old_jobs';`
- [ ] Settings.php has no syntax errors
- [ ] Logged in as admin user
- [ ] Database has email jobs with status='completed' or 'failed'

### Step 1: Access Maintenance Tab
1. Open browser and go to: `http://localhost/email_dispatcher_suite/public/settings.php`
2. Sign in with admin account (e.g., totoh.faturohman or abdul.malik)
3. Look for "🔧 Maintenance" tab button
4. Click the Maintenance tab
5. **Expected**: See green "✓ Jalankan Cleanup" button

### Step 2: Test Modal Opening
1. Click "✓ Jalankan Cleanup" button
2. **Expected**: Modal dialog appears with:
   - Title: "🔧 Database Cleanup Confirmation"
   - Warning message about permanent deletion
   - Password input field (empty)
   - Two buttons: "✓ Jalankan Cleanup" and "Batal" (Cancel)

### Step 3: Test Modal Closing
1. Click "Batal" button or the X button
2. **Expected**: Modal closes immediately
3. Button should be clickable again

### Step 4: Test with Wrong Password
1. Click "✓ Jalankan Cleanup" button again
2. Type wrong password (e.g., "wrongpass123")
3. Click "✓ Jalankan Cleanup" button in modal
4. **Expected in order**:
   - Confirmation popup: "⚠️ Ini akan menghapus email jobs lama (>90 hari) secara permanen. Lanjutkan?"
   - Click OK on popup
   - **Result**: Red error message appears: "Password tidak sesuai. Cleanup dibatalkan."

### Step 5: Test with Correct Password
1. Click "✓ Jalankan Cleanup" button again
2. Type correct password for admin user
3. Click "✓ Jalankan Cleanup" button in modal
4. **Expected in order**:
   - Confirmation popup: "⚠️ Ini akan menghapus email jobs lama (>90 hari) secara permanen. Lanjutkan?"
   - Click OK on popup
   - **Result**: Green success message: "✅ Database cleanup berhasil dilakukan! Email lama (>90 hari) telah dihapus."

### Step 6: Verify Cleanup Executed
1. Open MySQL client or phpMyAdmin
2. Run: `SELECT COUNT(*) FROM mail_jobs WHERE status IN ('completed','failed') AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);`
3. **Expected**: Should return 0 or fewer rows than before

### Step 7: Verify Audit Logging
1. Check security_logs table (if it exists)
2. Run: `SELECT * FROM security_logs WHERE user_id = [admin_id] ORDER BY created_at DESC LIMIT 5;`
3. **Expected**: Should see entry like:
   - event_type: 'database_cleanup'
   - event_details: 'Executed cleanup_old_jobs(90)'
   - timestamp: Current time

### Step 8: Test Non-Admin Access
1. Log out and login as regular user (non-admin)
2. Navigate to settings.php
3. **Expected**: No "🔧 Maintenance" tab visible

---

## Common Issues & Solutions

### Issue: Button doesn't appear
**Possible Causes**:
1. Not logged in as admin
2. Page not set to tab=maintenance
3. PHP if condition not working

**Solution**:
- Verify you're logged in: Check $_SESSION in PHP
- Add `?tab=maintenance` to URL manually
- Check browser console (F12) for JavaScript errors

### Issue: Modal doesn't open when clicking button
**Possible Causes**:
1. JavaScript function not defined
2. Modal ID doesn't match
3. Browser JavaScript disabled

**Solution**:
- Press F12, go to Console tab
- Type: `openMaintenanceModal()` and press Enter
- Should open modal
- Check for JavaScript errors in console

### Issue: Form won't submit
**Possible Causes**:
1. Password field empty
2. CSRF token invalid/missing
3. JavaScript validation blocking submission

**Solution**:
- Ensure password is entered
- Check page source for `<input name="csrf_token">`
- Check browser console for validation messages

### Issue: Password verification fails
**Possible Causes**:
1. Wrong password typed
2. User's password_hash corrupted in DB
3. password_verify() function issue

**Solution**:
- Verify password is correct for current admin user
- Check password_hash in users table: `SELECT password_hash FROM users WHERE id = [admin_id];`
- Try changing admin password and trying again

### Issue: Cleanup executes but nothing deleted
**Possible Causes**:
1. No old completed/failed jobs exist
2. All jobs are newer than 90 days
3. Procedure not deleting (trigger issue)

**Solution**:
- Check: `SELECT COUNT(*) FROM mail_jobs WHERE status IN ('completed','failed');`
- Check: `SELECT COUNT(*) FROM mail_jobs WHERE status IN ('completed','failed') AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);`
- Try: `CALL cleanup_old_jobs(90);` manually in MySQL

### Issue: Database cleanup error "Cannot add child row"
**Cause**: Foreign key constraint violation when deleting
**Solution**:
- Don't delete parent records before children (procedure should handle this)
- Check mail_job_items table has correct foreign key
- Verify cascade delete rules

---

## Manual Testing Commands

### Test Procedure Directly
```sql
-- Check if procedure exists
SHOW PROCEDURE STATUS WHERE Name='cleanup_old_jobs';

-- Create test data (optional)
-- Insert a completed job with old date:
-- INSERT INTO mail_jobs(created_by, subject, body, cc, mode, status, created_at) 
-- VALUES(5, 'Test', 'Body', '', 'by_similarity', 'completed', DATE_SUB(NOW(), INTERVAL 100 DAY));

-- Count jobs before cleanup
SELECT COUNT(*) as before_cleanup FROM mail_jobs;
SELECT COUNT(*) as old_jobs FROM mail_jobs WHERE status IN ('completed','failed') AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Run cleanup
CALL cleanup_old_jobs(90);

-- Count jobs after cleanup
SELECT COUNT(*) as after_cleanup FROM mail_jobs;
```

### Test CSRF Token
```php
// In PHP file, check:
$csrf = SecurityManager::generateCSRFToken();
echo "CSRF Token: " . $csrf; // Should output long random string

// Verify it in HTML:
// <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
```

### Test Password Verification
```php
// Get admin's password hash
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([5]); // admin user ID
$user = $stmt->fetch();
echo "Hash: " . $user['password_hash'];

// Test verification
$password = 'testpass123';
password_verify($password, $user['password_hash']); // Should return true/false
```

---

## Expected Database Changes After Cleanup

### Before Cleanup
```
mail_jobs table:
- 32 total jobs
- 15 completed jobs older than 90 days
- 8 failed jobs older than 90 days
- 55 mail_job_items (children of old jobs)

Total: 32 jobs, 55 items
```

### After Cleanup (90-day threshold)
```
mail_jobs table:
- 9 total jobs (32 - 23 old ones)
- 0 completed jobs older than 90 days
- 0 failed jobs older than 90 days
- 12 mail_job_items (only children of newer jobs remain)

Total: 9 jobs, 12 items
```

---

## Security Audit

### What's Protected ✅
- [x] Only admins can access maintenance tab
- [x] Only admins can execute cleanup
- [x] Requires password confirmation
- [x] CSRF token required
- [x] All actions logged in security_logs
- [x] Only deletes completed/failed jobs (safe)
- [x] Only deletes jobs >90 days old (won't delete recent data)

### What's Not Protected ⚠️
- [ ] Rate limiting (admin can spam cleanup repeatedly)
- [ ] IP whitelisting (any IP can access if logged in)
- [ ] Execution time limit (huge cleanup could timeout)

### Recommendations for Production
1. Add rate limiting: max 1 cleanup per day per admin
2. Add email notification when cleanup completes
3. Add backup before cleanup (if applicable)
4. Monitor mail_jobs table size regularly

---

## Browser Developer Tools Testing

### Console Commands to Test
```javascript
// Test if openMaintenanceModal function exists
typeof openMaintenanceModal === 'function'  // Should return: true

// Test if closeMaintenanceModal function exists
typeof closeMaintenanceModal === 'function'  // Should return: true

// Test opening modal programmatically
openMaintenanceModal()  // Modal should appear

// Test closing modal programmatically
closeMaintenanceModal()  // Modal should disappear

// Check if modal HTML exists in DOM
document.getElementById('maintenanceModal')  // Should return: <div id="maintenanceModal"...>

// Check if password field exists
document.getElementById('adminPassword')  // Should return: <input type="password" id="adminPassword"...>
```

### Network Tab Testing
When submitting cleanup form:
1. F12 → Network tab
2. Click "✓ Jalankan Cleanup" in modal
3. Confirm the popup
4. **Expected**: POST request to `settings.php?tab=maintenance`
5. **Response**: 200 OK with page HTML showing success message
6. **Headers should include**:
   - Content-Type: application/x-www-form-urlencoded
   - csrf_token: [random token]
   - action: cleanup_database
   - admin_password: [hashed or plain]

---

## Success Indicators

✅ **You've completed testing successfully when**:
1. Admin can access maintenance tab
2. Modal opens on button click
3. Modal closes on cancel/X
4. Empty password shows error
5. Wrong password shows error
6. Correct password + confirmation executes cleanup
7. Success message appears after execution
8. Audit log shows database_cleanup entry
9. Old jobs are actually deleted from database
10. Non-admin users cannot see maintenance tab

---

## Support Contacts

For issues with:
- **Database cleanup logic**: Check cleanup_old_jobs procedure in database
- **Authentication/Password**: Check SecurityManager class
- **Modal display**: Check CSS .modal class in settings.php
- **JavaScript errors**: Press F12, check Console tab
- **CSRF issues**: Verify SecurityManager::generateCSRFToken() works

