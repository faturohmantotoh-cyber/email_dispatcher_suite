# Avatar Setup & Troubleshooting

## Quick Setup

1. **Buka URL setup**: http://localhost/email_dispatcher_suite/public/setup_avatar.php
   - Script akan otomatis:
     - Menambahkan kolom `avatar` ke table `users` (jika belum ada)
     - Membuat folder `storage/avatars/` untuk custom uploads
     - Verify preset avatar files

2. **Refresh Dashboard**: http://localhost/email_dispatcher_suite/public/index.php
   - Hard refresh: `Ctrl+Shift+R` (Windows) atau `Cmd+Shift+R` (Mac)
   - Click avatar di header untuk test

## Features

### Preset Avatars (6 pilihan)
- Pilih dari 6 avatar bawaan
- SVG format (scalable)
- Instant upload, langsung terlihat

### Custom Upload
- Upload JPG, PNG, SVG, WebP
- Max file size: 5MB
- Auto-delete file lama saat update

## Troubleshooting

### Error: "Column not found: 1054 Unknown column 'avatar'"
**Solusi:**
1. Buka: http://localhost/email_dispatcher_suite/public/setup_avatar.php
2. Verify setup berhasil (lihat green checkmarks)
3. Jika tetap error, jalankan query manual di phpMyAdmin:
```sql
ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL AFTER password_hash;
```

### Error: "Server error: SQLSTATE[...]"
**Solusi:**
1. Check `storage/avatars/` folder readable/writable:
   - Windows: Right-click folder → Properties → Security
   - Linux/Mac: `chmod 755 storage/avatars`
2. Clear browser cache: `Ctrl+Shift+Delete`
3. Try upload lagi

### Upload tidak bisa dipilih/tidak bisa save
**Solusi:**
1. Check file format: JPG, PNG, SVG, atau WebP saja
2. Check file size: Max 5MB
3. Buka browser console: `F12` → Console
4. Lihat error message untuk detail

## API Endpoints

### Upload Custom Avatar
```
POST /public/api_upload_avatar.php
Content-Type: multipart/form-data

File: avatar (JPG/PNG/SVG/WebP max 5MB)

Response: {
  "success": true,
  "avatar": "storage/avatars/avatar_1_1709650800.jpg",
  "message": "File berhasil diupload"
}
```

### Update User Avatar
```
POST /public/api_update_avatar.php
Content-Type: application/json

{
  "avatar": "avatar1.svg"  // atau "storage/avatars/..."
}

Response: {
  "success": true,
  "avatar": "avatar1.svg",
  "message": "Avatar berhasil diubah"
}
```

## Database Schema

```sql
-- Avatar column di table users
ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL;

-- Example data:
-- avatar: "avatar1.svg" (preset)
-- avatar: "storage/avatars/avatar_123_1709650800.jpg" (custom)
-- avatar: NULL (using initial letter as default)
```

## File Locations

```
assets/img/avatars/          ← Preset avatar SVG files
  avatar1.svg
  avatar2.svg
  ... avatar6.svg

storage/avatars/             ← Custom uploaded avatars
  avatar_123_1709650800.jpg
  avatar_456_1709650850.png
```

## Best Practices

1. **Before first use**: Run setup_avatar.php
2. **For production**: Set folder permissions to 755
3. **Regular cleanup**: Delete old avatar files from storage/avatars/ occasionally
4. **Backup**: Keep preset avatars in assets/img/avatars/
