# Email Template System - Testing Guide

## ✅ Implementation Status

All components have been successfully implemented:

### Database
- **Tables Created**: `email_templates`, `template_group_links`, `template_group_order_links`
- **Sample Templates**: 3 professional email templates pre-loaded
- **Migration Status**: ✅ `db/migration_email_templates.sql` executed successfully

### Backend API
- **File**: `/public/api_templates.php` (450+ lines)
- **Endpoints**: Full CRUD + linking operations
- **Security**: CSRF protection, role-based access control, security logging

### Admin Interface
- **File**: `/public/templates.php` (450+ lines)
- **Features**: Template management with Quill.js WYSIWYG editor
- **Tabs**: 
  1. List all templates
  2. Create/edit templates
  3. Link templates to groups/group_orders

### Compose Integration
- **File**: `/public/compose.php` (enhanced)
- **AJAX Handlers**: 
  - `?ajax=get_template_by_group&group_id=X`
  - `?ajax=get_template_by_group_order&group_order_id=X`
- **JavaScript**: Auto-loads and populates template when group is selected
- **Behavior**: Template loads into Quill editor, fully editable

---

## 🧪 Testing Workflow

### Step 1: Create Sample Group (if not exists)
```sql
INSERT INTO `groups` (`name`) VALUES ('Sales Team Test') 
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id);
-- This creates a group with id=1 (or returns existing)
```

### Step 2: Access Templates Admin Page
1. Login to the application (admin/user account)
2. Navigate to: `http://localhost/email_dispatcher_suite/public/templates.php`
3. Should see three pre-loaded templates

### Step 3: Link Template to Group
1. Go to **"🔗 Konfigurasi Link"** tab in templates.php
2. Select a template from the dropdown (e.g., "Template Default - Professional")
3. Select a group from the "Grup" dropdown (e.g., "Sales Team Test")
4. Click "Simpan Link" (Save Link)
5. ✅ Should see confirmation: "Template linked successfully"

### Step 4: Test Compose with Template
1. Go to: `http://localhost/email_dispatcher_suite/public/compose.php`
2. Click **"📇 Buka Kontak (Slider)"** button
3. In the drawer, expand **"Filter Grup"** dropdown
4. Check the group you just linked a template to (e.g., "Sales Team Test")
5. Click **"Terapkan"** (Apply) button
6. ✅ **EXPECTED**: 
   - Template auto-loads into email body
   - SweetAlert notification appears: "Template dimuat!" (Template loaded!)
   - Email body editor shows template HTML content
   - Content is fully editable

### Step 5: Edit Template Content
1. With template loaded in compose page
2. Click into the email body editor
3. Modify, add, or delete text
4. Content should be fully editable without restrictions
5. ✅ All changes preserved when composing

### Step 6: Send Email (Optional)
1. Fill in subject and any other fields
2. Make sure recipients count shows > 0
3. Click **"3) Preview & Cocokkan (Similarity)"**
4. Should proceed normally with edited template content

---

## 🔍 Troubleshooting

### Template Not Loading?
- Check database: `SELECT * FROM email_templates;`
- Verify link exists: `SELECT * FROM template_group_links;`
- Check browser console (F12) for JS errors
- Verify AJAX return: Open DevTools Network tab and check `?ajax=get_template_by_group` response

### Quill Editor Not Showing Template?
- Verify Quill.js loaded (should see toolbar above editor)
- Check that template body contains valid HTML
- Try refreshing page and re-selecting group

### Permission Denied?
- Must be logged in as admin to see `/public/templates.php`
- Check `$_SESSION['user']['role']` in compose.php for AJAX
- Verify `lib/security.php` SecurityManager is properly initialized

### Group Not Appearing?
- Verify group exists: `SELECT * FROM groups;`
- Verify group members exist: `SELECT * FROM group_members WHERE group_id=1;`
- Refresh group list in filter dropdown

---

## 📊 Database Schema Reference

### email_templates
```sql
CREATE TABLE email_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  description TEXT,
  body MEDIUMTEXT NOT NULL,          -- HTML content
  template_type ENUM('standalone', 'group', 'group_order'),
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
)
```

### template_group_links
```sql
CREATE TABLE template_group_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  group_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_template_group (template_id, group_id)
)
```

### template_group_order_links
```sql
CREATE TABLE template_group_order_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  group_order_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_template_grouporder (template_id, group_order_id)
)
```

---

## 📝 Key Features Implemented

✅ **Template Creation**: Admin can create/edit templates with rich text editor
✅ **Group Linking**: Admin can link templates to specific groups
✅ **Group Order Linking**: Admin can link templates to group orders
✅ **Auto-Population**: Template auto-loads when group selected in compose
✅ **Full Editability**: Users can freely edit/modify template content
✅ **Security**: CSRF tokens on all forms, role-based access control
✅ **Logging**: All admin actions logged to security_logs table
✅ **RESTful API**: `/public/api_templates.php` provides programmatic access

---

## 🎯 User Workflow

1. **Admin**: Creates email templates via `/public/templates.php`
2. **Admin**: Links templates to groups/group orders via template config tab
3. **User**: Goes to `/public/compose.php` (compose email page)
4. **User**: Opens contact slider and selects a group
5. **User**: Template auto-populates in email body
6. **User**: Edits/customizes template content as needed
7. **User**: Completes email composition and sends

This dramatically improves efficiency for group email campaigns!

---

## Next Steps (Optional)

- Create more templates for different use cases
- Document email template best practices in README.md
- Consider adding template usage statistics to admin dashboard
- Implement template version history (update email_templates with version field)
- Add inline template preview in group selection dropdown

