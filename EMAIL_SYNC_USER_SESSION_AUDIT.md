# 📋 Email Synchronization & User Session Audit Report

**Date**: March 6, 2026  
**Status**: ⚠️ PARTIALLY CONFIGURED (See issues below)

---

## ✅ Working Correctly

### 1. Email Sender Uses Logged-In User Account
**Status**: ✅ VERIFIED

**Files**: 
- `config.php` (Line 35-42)
- `send.php` (Line 153)
- `contacts.php` (Line 162)

**Implementation**:
```php
function get_sender_account() {
    if (!empty($_SESSION['user']['email'])) {
        return $_SESSION['user']['email'];  // Use logged-in user's email
    }
    return OUTLOOK_ACCOUNT_DEFAULT;  // Fallback
}
```

**Result**: ✅ Email sent FROM the logged-in user's account
- Admin@local sends FROM admin@local
- User1 sends FROM user1's email
- User2 sends FROM user2's email

---

### 2. Contact Synchronization Uses User Account
**Status**: ✅ VERIFIED

**File**: `contacts.php` (Line 162)

**Process**:
1. User clicks "Sync Kontak dari Outlook"
2. Gets their email: `$account = get_sender_account()`
3. Runs PowerShell to export contacts from THEIR Outlook
4. Imports to contacts table

**Result**: ✅ Each user syncs contacts from THEIR OWN Outlook account

---

### 3. Compose Page Displays User Email
**Status**: ✅ VERIFIED

**File**: `compose.php` (Line 1492)

**Display**:
```html
<strong>📧 Pengirim:</strong> <?= e(get_sender_account()) ?>
```

**Result**: ✅ Shows which user account email is being used

---

## ❌ Issue Found - Missing User ID Tracking

### Problem: mail_jobs table has NO user_id field

**Current Schema** (`db/install.sql` Line 40):
```sql
CREATE TABLE mail_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME,
  subject VARCHAR(255),
  body MEDIUMTEXT,
  cc VARCHAR(500),
  mode ENUM(...),
  status ENUM(...)
  -- ❌ NO user_id field!
)
```

**What This Means**:
- ✅ Email IS sent from correct user account (verified above)
- ✅ Email ADDRESSES appear correct
- ❌ Database doesn't record WHO created the job
- ❌ No audit trail linking job to user
- ❌ Multiple users can see all jobs in logs (potential security issue)

---

## 🔍 Detailed Analysis

### Current Flow

```
User Login
    ↓
Session['user']['email'] = "user@example.com"
    ↓
Click "Send Email"
    ↓
send.php:
  - $account = get_sender_account()  ✅
  - INSERT INTO mail_jobs           ❌ NO user_id
  - PowerShell -Account $account    ✅
    ↓
Result: Email sent correctly, but job not linked to user in database
```

### The Risk

```
User 1 (admin@local):
  - Sends 5 emails
  - Creates 5 mail_jobs entries

User 2 (user1@example.com):
  - Logs in
  - Goes to logs.php
  - Sees ALL jobs (including User 1's) ❌
  - Could potentially modify/delete User 1's jobs ❌
```

---

## ✅ Solution: Add user_id to mail_jobs

### Step 1: Modify Database Schema

```sql
ALTER TABLE mail_jobs
ADD COLUMN created_by INT NOT NULL AFTER id,
ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE;

-- Create index for faster queries
ALTER TABLE mail_jobs
ADD INDEX idx_created_by (created_by),
ADD INDEX idx_created_by_status (created_by, status);
```

### Step 2: Update send.php

**Before**:
```php
$stmt = $pdo->prepare("INSERT INTO mail_jobs(subject, body, cc, mode, status) 
                      VALUES(?, ?, ?, 'by_similarity', 'processing')");
$stmt->execute([$subject, $body, $cc]);
```

**After**:
```php
// Add user ID to job record
$stmt = $pdo->prepare("INSERT INTO mail_jobs(created_by, subject, body, cc, mode, status) 
                      VALUES(?, ?, ?, ?, 'by_similarity', 'processing')");
$stmt->execute([$_SESSION['user']['id'], $subject, $body, $cc]);
```

### Step 3: Update logs.php

**Filter jobs by current user**:
```php
$stmt = $pdo->prepare("
    SELECT j.id, j.subject, j.status, ...
    FROM mail_jobs j
    LEFT JOIN mail_job_items i ON i.mail_job_id = j.id
    WHERE j.created_by = ?  -- ✅ NEW: Filter by user
    GROUP BY j.id
    ORDER BY j.created_at DESC
");
$stmt->execute([$_SESSION['user']['id']]);
$jobs = $stmt->fetchAll();
```

### Step 4: Update compose.php (if needed)

Confirm that groups/templates are also user-scoped if needed.

---

## 📋 Implementation Checklist

### Phase 1: Database Migration
- [ ] Back up database
- [ ] Add `created_by` column to `mail_jobs`
- [ ] Add foreign key to `users` table
- [ ] Add indexes for performance
- [ ] Set existing rows: `UPDATE mail_jobs SET created_by = 1` (admin)

### Phase 2: Code Changes
- [ ] Update `send.php` to record user_id
- [ ] Update `logs.php` to filter by user
- [ ] Test multi-user log isolation
- [ ] Verify admin can still see all jobs (if needed)

### Phase 3: Testing & Validation
- [ ] User 1 sends email → appears in User 1 logs only
- [ ] User 2 sends email → appears in User 2 logs only
- [ ] Admin sees all jobs (if designed) OR only their own
- [ ] No data loss or corruption
- [ ] Performance impact minimal

### Phase 4: Audit Trail
- [ ] Consider adding `updated_by` column
- [ ] Log modifications with timestamps
- [ ] Create audit_log for compliance

---

## 🔐 Security Implications

### Current (No user_id):
- **Multi-user visibility**: ⚠️ Users can see others' emails sent
- **Audit trail**: ❌ Can't track who sent what
- **Data isolation**: ❌ No clear ownership
- **Compliance**: ❌ Fails audit requirements

### After Adding user_id:
- **Multi-user visibility**: ✅ Each user sees only their jobs
- **Audit trail**: ✅ Full history of who did what
- **Data isolation**: ✅ Clear ownership and scope
- **Compliance**: ✅ Meets audit requirements

---

## 📊 Affected Tables

| Table | Issue | Status | Priority |
|-------|-------|--------|----------|
| `mail_jobs` | No user_id | High | P1 |
| `mail_job_items` | Inherits from mail_jobs | High | P1 |
| `email_templates` | Has created_by ✅ | OK | - |
| `groups` | No user_id | Medium | P2 |
| `contacts` | No user_id | Medium | P2 |

---

## 🎯 Recommendation

**Implement IMMEDIATELY (Priority P1)**:

1. Add `created_by INT NOT NULL` to `mail_jobs`
2. Modify `send.php` to insert user ID
3. Modify `logs.php` to filter by user
4. Test multi-user scenarios

**This is important for**:
- ✅ Data security
- ✅ Multi-user deployments
- ✅ Audit compliance
- ✅ Preventing accidental cross-user data access

---

## 📝 Current Session Verification

| Component | Uses Session? | Correctly? | User-Specific? | Issue |
|-----------|---------------|-----------|----------------|-------|
| get_sender_account() | ✅ Yes | ✅ Yes | ✅ Yes | None |
| send.php email sending | ✅ Yes | ✅ Yes | ✅ Yes | None |
| contacts.php sync | ✅ Yes | ✅ Yes | ✅ Yes | None |
| mail_jobs recording | ❌ No | N/A | ❌ No | **NEEDS FIX** |
| logs.php viewing | ✅ Yes | ⚠️ Partial | ❌ No | **NEEDS FIX** |

---

## Summary

✅ **Email sending FROM user account**: Working correctly  
✅ **Contact sync FROM user account**: Working correctly  
❌ **Recording which user created job**: NOT implemented  
❌ **Filtering jobs by user**: NOT implemented  

**Next Step**: Implement user_id tracking in mail_jobs table (see Implementation Checklist above)

