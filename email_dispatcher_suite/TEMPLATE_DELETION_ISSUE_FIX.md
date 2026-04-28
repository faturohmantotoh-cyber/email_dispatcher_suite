# Template Deletion Issue - Root Cause & Fix

**Status**: ✅ **FIXED**

Date Fixed: March 6, 2026

## The Problem

When you deleted the user `admin@local`, **all email templates that user created were automatically deleted from the database**.

You didn't manually delete the templates - the database automatically deleted them due to a dangerous foreign key constraint.

## Root Cause

The `email_templates` table had this constraint:

```sql
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
```

**What "ON DELETE CASCADE" means**:
- When a user is deleted from the `users` table
- Automatically delete ALL templates where `created_by` = that user's ID
- This happens automatically - no confirmation, no warning

**Why this is dangerous**:
- Deleting a user account destroys their work (templates)
- Intended for deleting related records (like user's session logs), NOT important content
- Best practice: Use `ON DELETE SET NULL` instead

## Solution Applied

### Step 1: Changed the Foreign Key Constraint ✅
```sql
-- Before (DANGEROUS):
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE

-- After (SAFE):
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
```

### Step 2: Made `created_by` Column Nullable ✅
```sql
-- Before: created_by INT NOT NULL
-- After:  created_by INT NULL
```

Now when a user is deleted:
- Their templates remain in the database
- The `created_by` field is set to NULL
- No data loss

### Step 3: Restored Templates ✅
The 3 default templates were restored:
1. Template Default - Professional
2. Template Pengumuman - Resmi
3. Template Laporan - Bulanan

## Files Updated

1. **Database** - Constraint modified live
   - ✅ Foreign key changed to ON DELETE SET NULL
   - ✅ created_by made nullable
   
2. **Migration file** - For clean installations
   - ✅ `/db/migration_email_templates.sql` updated
   - ✅ created_by INT NULL with correct comment
   - ✅ Constraint set to ON DELETE SET NULL

3. **New files created**
   - `/db/restore_templates.sql` - Template restoration script
   - `/db/restore_templates_fix_cascade.sql` - Full fix documentation
   - `TEMPLATE_DELETION_ISSUE_FIX.md` - This file

## How to Prevent This in Future

### Best Practice for Foreign Keys

**For deleting users or accounts:**

Use `ON DELETE SET NULL` if the related data should be preserved:
```sql
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
```

Use `ON DELETE CASCADE` ONLY if related data should be deleted:
```sql
-- Example: Delete user's session logs when user deleted
FOREIGN KEY (session_user_id) REFERENCES users(id) ON DELETE CASCADE
```

**Rule of Thumb:**
- **User's content** (templates, posts, files) → Use `SET NULL` ✅
- **User's metadata** (sessions, temp data) → Use `CASCADE` ✅
- **Sensitive data** (payments, audits) → Use `RESTRICT` ✅

### Review Other Tables

Check if other tables have similar dangerous CASCADE constraints:

```sql
-- See all CASCADE delete rules in your database
SELECT 
  TABLE_NAME,
  COLUMN_NAME,
  CONSTRAINT_NAME,
  REFERENCED_TABLE_NAME,
  DELETE_RULE
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r
  ON k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
WHERE DELETE_RULE = 'CASCADE'
  AND TABLE_SCHEMA = 'email_dispatcher';
```

## What Happens Now

✅ **If you delete a user account**:
- Their templates remain in database
- Their `created_by` field becomes NULL (showing "Unknown" in UI)
- All template-to-group and template-to-group-order links remain intact
- Templates are still usable

❌ **If you permanently delete a template**:
- The template is gone (intentional delete)
- Template-to-group links are removed (CASCADE is correct here)

## Testing

To verify the fix works:

```sql
-- Create a test user
INSERT INTO users (username, email, role, created_at) 
VALUES ('test_user', 'test@example.com', 'admin', NOW());

-- Create a template under that user
INSERT INTO email_templates (name, description, body, template_type, created_by)
VALUES ('Test Template', 'Test', '<p>Test</p>', 'standalone', LAST_INSERT_ID()-1);

-- Delete the test user
DELETE FROM users WHERE username = 'test_user';

-- Verify template still exists
SELECT * FROM email_templates WHERE name = 'Test Template';
-- Result: Template exists with created_by = NULL ✅
```

## Related Tables to Check

These tables also use CASCADE deletes (review if appropriate):

```sql
-- Show all foreign keys in the database
SELECT 
  TABLE_NAME,
  CONSTRAINT_NAME,
  COLUMN_NAME,
  REFERENCED_TABLE_NAME,
  DELETE_RULE
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r
  ON k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
WHERE TABLE_SCHEMA = 'email_dispatcher'
  AND DELETE_RULE = 'CASCADE'
ORDER BY TABLE_NAME;
```

## Summary

| Aspect | Before | After |
|--------|--------|-------|
| User deletion affects templates | ❌ Deletes templates | ✅ Preserves templates |
| created_by field | NOT NULL | NULL (nullable) |
| Constraint rule | CASCADE | SET NULL |
| Data recovery | Lost forever | Preserved with NULL creator |
| Templates linked to groups | Deleted | Still linked |

---

**Key Takeaway**: Always use `ON DELETE SET NULL` for user-created content to prevent accidental data loss from account deletion.
