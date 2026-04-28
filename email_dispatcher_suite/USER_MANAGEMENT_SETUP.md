# ✨ User Management & Authorization System - Implementasi

## 📦 Fitur yang Ditambahkan

### 1. **Database Schema Update** ✅
- Kolom `role` ditambahkan ke tabel `users`
- Role options: `admin`, `user` (default), `viewer`
- Migration script tersedia di: `public/migrate_add_roles.php`

### 2. **Settings Page Enhancement** ✅
- **Tab 1**: 🔐 Ubah Password (untuk semua user)
- **Tab 2**: 👥 Kelola User (hanya admin)
  - Form to tambah user baru
  - Tabel list semua users
  - Dropdown untuk ubah role user
  - Tombol hapus user (dengan validasi)

### 3. **Authorization Levels** ✅
```
📊 Admin (Level 3)
├─ Akses penuh semua fitur
├─ Mengelola user
├─ Ubah role pengguna
└─ Akses pengaturan sistem

📊 User (Level 2) - Default
├─ Kirim email
├─ Lihat logs
└─ Ubah password sendiri

📊 Viewer (Level 1)
├─ Lihat contacts (read-only)
├─ Lihat logs & rekap
└─ Ubah password sendiri
```

### 4. **Utility Functions** ✅
Ditambahkan ke `lib/util.php`:
- `hasRole($roles, $user)` - Check jika user punya role tertentu
- `requireRole($roles, $redirect)` - Enforce role, redirect jika tidak allowed
- `getRoleLevel($role)` - Get numeric level dari role
- `canPerform($minimumRole, $user)` - Check permission berdasar hierarchy

### 5. **Session Integration** ✅
- Role disimpan di session: `$_SESSION['user']['role']`
- Role diambil dari database saat login
- Session updated automatically di `auth_login.php`

## 🚀 Cara Menggunakan

### Setup Database
1. Run migration script (hanya 1x):
   ```
   http://localhost/email_dispatcher_suite/public/migrate_add_roles.php
   ```

2. Atau run SQL langsung:
   ```sql
   ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'user' AFTER password_hash;
   UPDATE users SET role = 'admin' WHERE username = 'admin@local';
   ```

### Login Pertama
- **Username**: `admin@local`
- **Password**: `password`

### Tambah User Baru
1. Login dengan akun admin
2. Klik menu **Pengaturan** → Tab **👥 Kelola User**
3. Isi form "Tambah User Baru":
   - Username: `user@company.com`
   - Email: `user@company.com`
   - Nama Lengkap: `John Doe`
   - Role: Pilih dari dropdown (admin, user, viewer)
   - Password: `password123`
4. Klik **✓ Buat User Baru**

### Ubah Role User
1. Admin login → Pengaturan → Kelola User
2. Pada tabel user, klik dropdown di kolom Role
3. Pilih role baru
4. Otomatis tersimpan

### Hapus User
1. Admin login → Pengaturan → Kelola User
2. Pada tabel user, klik **🗑 Hapus**
3. Konfirmasi di dialog
4. User dihapus

## 📋 Files Modified/Created

| File | Status | Keterangan |
|------|--------|-----------|
| `public/settings.php` | ✅ Modified | Tab interface, user management |
| `public/auth_login.php` | ✅ Modified | Include role di session |
| `public/migrate_add_roles.php` | ✨ Created | Migration script |
| `lib/util.php` | ✅ Modified | Role checking functions |
| `ROLES_AUTHORIZATION.md` | ✨ Created | Dokumentasi lengkap |

## 🛠️ Menggunakan Role di Custom Code

### Contoh 1: Redirect jika bukan admin
```php
<?php
require_once __DIR__ . '/../lib/util.php';

// Hanya admin yang boleh akses halaman ini
requireRole('admin', 'index.php');

// Rest of code...
?>
```

### Contoh 2: Custom logic berdasar role
```php
<?php
if (hasRole('admin')) {
    echo "Admin panel di sini";
} elseif (hasRole('user')) {
    echo "User dashboard";
} else {
    echo "Viewer mode - read only";
}
?>
```

### Contoh 3: Check role hierarchy
```php
<?php
// Hanya admin dan user yang bisa kirim email
if (canPerform('user')) {
    // Show send form
} else {
    // Show "read-only" message
}
?>
```

## 🔒 Keamanan

✅ **Best Practices Implemented:**
- Password di-hash dengan bcrypt
- Role disimpan di database (trusted source)
- Admin tidak bisa menghapus dirinya
- Tidak bisa mengubah role sendiri
- Form CSRF protected
- Input validation di semua form
- HTML entities escaped (avoid XSS)

## ✨ Testing Checklist

- [ ] Login dengan admin@local
- [ ] Akses Pengaturan → Lihat 2 tabs
- [ ] Klik tab "👥 Kelola User"
- [ ] Isi form tambah user baru
- [ ] User baru muncul di tabel dengan role yang dipilih
- [ ] Ubah role user di dropdown
- [ ] Login sebagai user baru
- [ ] Verify user baru HANYA lihat tab "Ubah Password" (tidak ada tab Kelola User)
- [ ] Login sebagai admin, hapus user test
- [ ] Verify user terhapus dari tabel

## 📞 Support

Jika ada error:
1. Check PHP syntax: `php -l settings.php`
2. Check database: Verify `role` column exists
3. Check logs: `storage/logs/`
4. Verify migration ran: SELECT * FROM users;

---

**Status**: ✅ Ready for Production  
**Last Updated**: March 4, 2026  
**Version**: 1.0 User Management System
