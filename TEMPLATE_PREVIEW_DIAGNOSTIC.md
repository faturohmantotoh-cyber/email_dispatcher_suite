# Template Preview Diagnostic Guide

**Symptom**: Template preview modal not showing when selecting group order

## Quick Diagnostic Steps

### Step 1: Check Database (Admin Can Use)

Open your browser and visit this URL to see your template database status:

```
http://localhost/compose.php?ajax=debug_templates
```

You'll see a JSON response showing:
- `templates_count`: Number of templates in database
- `templates`: List of all templates
- `group_orders_count`: Number of group orders
- `group_order_links_count`: Number of template-to-group-order links
- `group_order_links`: Actual links (should show `template_id` → `group_order_id`)

**What to look for**:
- ✅ If `templates_count` > 0 and there are templates listed
- ✅ If `group_orders_count` > 0 and group orders exist
- ✅ If `group_order_links` has entries linking templates to group orders
- ❌ If any count is 0 or empty → **Data missing in database**

### Step 2: Browser Console Debugging

1. Open Compose page: `http://localhost/compose.php`
2. Press **F12** to open Developer Tools
3. Go to **Console** tab
4. Select a group order from the group order dropdown
5. Look for console messages starting with:
   - 🔍 "Trying to load template by GROUP ORDER ID: X"
   - 📥 "Template loading response:" followed by JSON

**What to look for**:

#### Good Response (Template Found)
```javascript
📥 Template loading response: {
  ok: true, 
  data: {
    id: 1, 
    name: "Template Name", 
    description: "...",
    body: "<html>..."
  }
}
```

#### Bad Response (No Template)
```javascript
📥 Template loading response: {
  ok: false,
  error: "No template found for this group order"
}
```

#### Error Response (API Error)
```javascript
// Check the network response for status 404, 400, or 500
```

### Step 3: Network Tab Debugging

1. Open Developer Tools (F12)
2. Go to **Network** tab
3. Select a group order in the compose page
4. Look for Request to `?ajax=get_template_by_group_order&group_order_id=X`
5. Click on it and check:
   - **Status**: Should be `200 OK`
   - **Response**: Should show JSON with template data

If Status is **404** → Template not found in database  
If Status is **500** → Database error  
If Status is **404 with error "No template found"** → Need to link template to group order

---

## Common Issues & Solutions

### Issue 1: "No template found for this group order"

**Root Cause**: Template exists but not linked to the group order

**Solution**:
1. Go to **Manage Templates** page
2. Edit the template you want to use
3. In "Link to Group Orders" section, select the group order
4. Click Save
5. Try again in Compose page

### Issue 2: "Invalid group_order_id" or "No group_order_contacts"

**Root Cause**: Group order exists but has no groups/contacts in it

**Solution**:
1. Go to **Manage Groups** or **Manage Group Orders** page
2. Make sure the group order has at least one group linked
3. Make sure that group has contacts
4. Try selecting again

### Issue 3: Console shows no messages at all

**Root Cause**: JavaScript error or function not being called

**Solution**:
1. Open Developer Tools Console (F12)
2. Check for any red error messages
3. Look for warnings like "Uncaught Error" or "Cannot read property"
4. If you see an error, note the line number and file

**Common JavaScript errors to look for**:
- `Swal is not defined` → SweetAlert2 not loaded
- `quill is not defined` → Quill editor not loaded
- `fetch is not a function` → Fetch API not available (browser too old)

### Issue 4: Response shows "N/A" or empty template body

**Root Cause**: Template exists but body/description is empty

**Solution**:
1. Go to **Manage Templates** page
2. Edit the template
3. Add content to the body
4. Save
5. Try again

---

## Manual Testing Steps

### If you have templates but no group order links:

**Via Compose Page**:
1. Open `compose.php`
2. Create a template if one doesn't exist
3. Click "Manage Templates" button
4. Edit a template
5. In "Link to Group Orders" section, check a group order
6. Click Save/Update
7. Go back to Compose
8. Select that group order and test

**Via MySQL** (Direct):
```sql
-- First, see what templates and group orders exist
SELECT * FROM email_templates;
SELECT * FROM group_orders;

-- Link template 1 to group order 1
INSERT INTO template_group_order_links (template_id, group_order_id) 
VALUES (1, 1);

-- Verify link was created
SELECT * FROM template_group_order_links;
```

---

## Browser Console Messages Reference

When selecting a group order, you should see these console messages (in order):

```
[Group selection handler fires]
🔍 Trying to load template by GROUP ORDER ID: 5
📥 Template loading response: {ok: true, data: {...}}
📋 Template data: {name: "My Template", desc: "...", bodyLength: 1234}
[SweetAlert2 modal should appear]
```

If any message is missing or shows an error, note which step fails.

---

## Quick Status Check

Run this in browser console to test:
```javascript
// Test if Swal is loaded
console.log('SweetAlert2 loaded:', typeof Swal !== 'undefined');

// Test if fetch works
fetch('?ajax=debug_templates').then(r => r.json()).then(d => console.log('Debug data:', d));

// Test if Quill is loaded
console.log('Quill loaded:', typeof quill !== 'undefined');
```

---

## Getting Help

If after these steps the issue persists, provide:
1. Output from `?ajax=debug_templates` endpoint
2. Browser console error messages (if any)
3. Network tab Status code for the template fetch request
4. Which group order(s) are affected
5. Whether groups have contacts in them

