# Sistem User Management & Otorisasi

## 📋 Role/Tingkatan Otorisasi

Aplikasi Email Dispatcher Suite memiliki 3 level otorisasi:

### 1. **Admin** (Level 3)
- ✅ Akses penuh ke semua fitur
- ✅ Mengirim email ke dari compose page
- ✅ Kelola user (tambah, edit, hapus)
- ✅ Ubah role pengguna lain
- ✅ Lihat log dan rekap
- ✅ Akses semua pengaturan sistem

### 2. **User** (Level 2) - Default
- ✅ Mengirim email melalui compose &upload
- ✅ Melihat hasil pengiriman di logs
- ✅ Mengubah password sendiri
- ❌ Tidak bisa mengelola user lain
- ❌ Tidak bisa mengubah role

### 3. **Viewer** (Level 1)
- ✅ Melihat contact, log, dan rekap (read-only)
- ✅ Mengubah password sendiri
- ❌ Tidak bisa mengirim email
- ❌ Tidak bisa upload
- ❌ Tidak bisa mengelola user

## 🔧 Cara Menambah User Baru

1. **Login sebagai Admin** (username: `admin@local`, password: `password`)
2. Klik menu **"Pengaturan"** di header
3. Klik tab **"👥 Kelola User"**
4. Di form **"Tambah User Baru"**, isi:
   - **Username** - untuk login (contoh: `user@company.com`)
   - **Email** - email pengguna
   - **Nama Lengkap** - nama tampilan di aplikasi
   - **Role/Otorisasi** - pilih dari dropdown
   - **Password Awal** - password yang user gunakan untuk login pertama
5. Klik **"✓ Buat User Baru"**

## 👤 Update Role User

Untuk mengubah role user yang sudah ada:

1. Login sebagai Admin
2. Masuk ke **Pengaturan → Kelola User**
3. Pada tabel "Daftar User Terdaftar", di baris user yang ingin diubah
4. Klik dropdown di kolom **"Role"** dan pilih role baru
5. Role akan otomatis tersimpan

## 🗑️ Menghapus User

1. Login sebagai Admin
2. Masuk ke **Pengaturan → Kelola User**
3. Pada tabel "Daftar User Terdaftar", klik tombol **"🗑 Hapus"** pada baris user
4. Konfirmasi penghapusan
5. User akan dihapus dari sistem

## 🛡️ Batasan Keamanan

- **Admin tidak bisa menghapus dirinya sendiri** - Minimal ada 1 admin dalam sistem
- **Tidak bisa mengubah role diri sendiri** - Gunakan admin lain atau ubah langsung di database
- **Password minimal 6 karakter** - Enforce keamanan minimal
- **Password disimpan dengan bcrypt** - Tidak pernah disimpan plaintext
- **Setiap login tercatat di session** - Bisa di-log untuk audit trail nantinya

## 📝 Database Schema

Kolom `users` table setelah update:

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(200) NOT NULL,
  display_name VARCHAR(200),
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'user',     -- NEW: admin, user, viewer
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_email (email)
) ENGINE=InnoDB;
```

## 💻 Menggunakan Fungsi Role di PHP Code

Untuk develop/extend fitur dengan role checking:

```php
<?php
require_once __DIR__ . '/../lib/util.php';

// Check if user is admin
if (!hasRole('admin')) {
    die("Akses ditolak");
}

// Check if user has any of these roles
if (!hasRole(['admin', 'user'])) {
    die("Akses ditolak");
}

// Redirect jika bukan admin
requireRole('admin', 'index.php');

// Check role level (higher number = more permissions)
if (canPerform('admin')) {
    // User can do admin tasks
}

// Get user info
$userRole = $_SESSION['user']['role'] ?? 'user';
?>
```

## 🔐 Role Hierarchy

```
viewer (1) < user (2) < admin (3)
```

Admin memiliki permissions lebih tinggi dari user dan viewer.

## ✨ Default User

- **Username**: `admin@local`
- **Password**: `password`
- **Role**: `admin`
- **Status**: User pertama saat instalasi

Untuk menambah user baru, gunakan menu Pengaturan dengan akun admin.

## 🔄 Migrasi Database

Jika update dari versi lama, jalankan script migrasi:

```bash
# Run this migration once:
http://localhost/email_dispatcher_suite/public/migrate_add_roles.php
```

Script ini akan:
- ✅ Menambah kolom `role` ke tabel `users` (jika belum ada)
- ✅ Set admin user ke role 'admin'
- ✅ Default user baru ke role 'user'

---

**Dibuat**: March 4, 2026  
**Version**: 1.0 User Management & Authorization System
