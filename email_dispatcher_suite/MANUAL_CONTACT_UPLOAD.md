# 📤 Manual Contact Upload Feature

## Overview
Fitur untuk upload kontak secara manual dari file CSV dengan kontrol penuh terhadap proses import:
- **Update, jangan delete**: Data existing tidak akan dihapus
- **Skip duplicate**: Email yang sudah ada di database akan skip (tidak diupdate)
- **Template management**: Download template atau langsung upload jika sudah punya
- **User-friendly**: Dialog interaktif dengan feedback yang jelas

## Cara Menggunakan

### 1. Akses Menu Upload Manual
1. Buka halaman: `http://localhost/email_dispatcher_suite/public/contacts.php`
2. Cari bagian "Sinkronisasi Outlook"
3. Klik tombol **"📤 Upload Manual Kontak"**

### 2. Dialog Step 1: Template Check
Sebuah dialog akan muncul dengan dua pilihan:

**A. Download Template** (jika belum punya file CSV)
- Klik tombol "⬇️ Download Template"
- File `contact_template_id_[NEXT_ID].csv` akan didownload
- Template berisi:
  - Header: `Name, Email`
  - 5 contoh baris
  - ID auto-increment akan dimulai dari nomor terakhir + 1

**B. Langsung Upload File** (jika sudah punya CSV)
- Klik tombol "📁 Sudah Ada, Upload File"
- Lanjut ke Step 2

### 3. Dialog Step 2: Upload File

#### Drag & Drop
- Drag file CSV ke area yang bertuliskan "📄 Drag & drop file CSV di sini"
- Atau klik area untuk browse file

#### File Requirements
- **Format**: CSV (`.csv`)
- **Size**: Maksimal 10MB
- **Header**: Harus memiliki kolom "Email" (case-insensitive)
- **Optional**: Kolom "Name" (jika tidak ada, akan digunakan email sebagai nama)

#### Contoh Format CSV
```csv
Name,Email
John Doe,john@example.com
Jane Smith,jane@example.com
"Vendor Name","vendor@company.com"
```

#### Header Variations (semua diterima)
✅ `Name,Email`
✅ `name,email` 
✅ `Display Name,Email Address`
✅ `Contact Name,Email`
✅ Kombinasi apapun asalkan ada kolom "Email"

### 4. Upload Progress & Result

#### During Upload
- Progress indicator menunjukkan "⏳ Uploading..."
- Progress bar mengisi dari 0-100%

#### Success Result
Ketika upload berhasil, akan menampilkan:
- ✅ **Upload Berhasil!**
- Tabel summary:
  - **Diproses**: Total baris dalam CSV
  - **Ditambahkan**: Kontak baru yang berhasil ditambahkan
  - **Skip (duplicate)**: Email yang sudah ada (tidak diupdate)
  - **Skip (kosong)**: Baris tanpa email
  - **Error**: Baris yang error (jika ada)

#### Error Result
- ❌ **Upload Gagal**
- Pesan error yang jelas
- Tombol retry atau cancel

### 5. Setelah Upload
Dua pilihan:
- **Unggah File Lain**: Upload file CSV lain
- **Selesai & Refresh**: Refresh halaman untuk melihat kontak terbaru

## Technical Details

### Database Logic
```
Database Operation:
1. Baca CSV dan ekstrak data
2. Check existing emails di database
3. Untuk setiap baris:
   - Jika email kosong → skip
   - Jika email sudah exist → skip
   - Jika email baru → INSERT ke database
```

### API Endpoint
**Endpoint**: `api_contact_upload.php`

**Action 1: Download Template**
```
GET /public/api_contact_upload.php?action=download_template
Response: CSV file dengan:
  - BOM UTF-8
  - Header: Name, Email
  - 5 contoh baris
  - Filename: contact_template_id_[NEXT_ID].csv
```

**Action 2: Upload & Process**
```
POST /public/api_contact_upload.php
Content-Type: multipart/form-data
Body:
  - contact_file: <file>

Response (JSON):
{
  "success": true,
  "summary": {
    "processed": 10,      // Total baris (header tidak dihitung)
    "inserted": 8,        // Kontak baru ditambahkan
    "skipped_duplicate": 1,  // Email sudah ada
    "skipped_empty": 0,    // Baris tanpa email
    "errors": 0,          // Baris error
    "notes": [...]        // Warning/info messages
  }
}
```

### Validation
File CSV harus memenuhi:
- ✅ File exists dan readable
- ✅ File size ≤ 10MB
- ✅ MIME type: text/csv, text/plain, application/csv, application/vnd.ms-excel
- ✅ Extension: `.csv`
- ✅ Header readable (encoded UTF-8 atau dengan BOM)
- ✅ Minimal header: `Email` (wajib)
- ✅ Email values tidak kosong (baris dengan email kosong akan skip)

### Data Storage
- **Table**: `contacts`
- **Fields**: `display_name`, `email`, `source` (= 'Manual Upload'), `last_synced`
- **ID**: Auto-increment dari database existing

### Conflict Resolution
```
Scenario: Email sudah ada di database
Action: SKIP (abaikan baris)
Reason: Mencegah duplikasi dan mempertahankan integritas data
Result: Email akan listed di summary sebagai "Skip (duplicate)"
```

## Examples

### Example 1: Simple Import
**CSV File Contents:**
```
Name,Email
PT Maju Jaya,sales@mayujaya.com
PT Sukses Bersama,info@sukses.com
```

**Result:**
- Diproses: 2 baris
- Ditambahkan: 2 kontak
- Skipped: 0

---

### Example 2: Mixed (New + Duplicate)
**CSV File Contents:**
```
Name,Email
New Vendor,newvendor@example.com
Existing Contact,john@example.com
Another New,another@example.com
```

**Scenario**: `john@example.com` sudah ada di database

**Result:**
- Diproses: 3 baris
- Ditambahkan: 2 kontak (newvendor, another)
- Skip (duplicate): 1 email (john@example.com)

---

### Example 3: Complex CSV
**CSV File Contents:**
```
Display Name,Email Address
"PT Manufaktur Indonesia","contact@manufaktur.co.id"
"Beberapa Elektronik Limited Company","info@elektronik.net"
Sales Manager,
"PT Import Export","sales@importexport.com"
```

**Result:**
- Diproses: 5 baris
- Ditambahkan: 3 kontak
- Skip (kosong): 1 baris (Sales Manager tanpa email)

---

## Troubleshooting

### Error: "File harus berformat CSV"
**Solution**: 
- Gunakan file dengan extension `.csv`
- Bukan `.xlsx` atau format lain
- Jika dari Excel, gunakan "Save As → CSV (Comma-delimited)"

### Error: "File terlalu besar (maks 10MB)"
**Solution**:
- Pisahkan file besar menjadi beberapa file
- Upload satu per satu

### Error: "CSV harus memiliki kolom Email"
**Solution**:
- Pastikan header mengandung kolom bernama "Email"
- Format header: `Email` atau `email` atau `EMAIL` (case-insensitive)
- Kolom Email harus di-isi untuk setiap baris

### Upload berhasil tapi kontak tidak muncul
**Solution**:
- Refresh halaman (F5 atau Ctrl+Shift+R)
- Check "Skip (duplicate)" di summary - email mungkin sudah ada
- Verifikasi di Daftar Kontak di bawah

### CSV corrupted atau format invalid
**Solution**:
- Download template baru
- Copy-paste data ke template
- Upload kembali

## Notes & Best Practices

1. **Backup**: Tidak ada delete, tapi sebaiknya backup database sebelum import besar
2. **Verification**: Selalu check summary setelah upload
3. **Templates**: Gunakan template yang didownload untuk format konsisten
4. **Batch Import**: Jika banyak file, bisa upload satu-satu tanpa khawatir duplikasi
5. **Name Field**: Jika tidak ada "Name" di CSV, email akan digunakan sebagai display_name
6. **Encoding**: Pastikan file CSV di-save dengan encoding UTF-8

## Related Features

- **Daftar Kontak**: Lihat semua kontak yang sudah diupload
- **Sinkronisasi Outlook**: Export kontak dari Outlook otomatis
- **Buat Grup**: Group kontak untuk email massal
- **Buat Grup Order**: Order multiple groups untuk email sequence

---

**Version**: 1.0  
**Last Updated**: March 2026  
**Status**: Ready for Production
