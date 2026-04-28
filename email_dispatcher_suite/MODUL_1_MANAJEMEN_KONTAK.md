# 📧 MODUL 1: MANAJEMEN KONTAK
**Email Dispatcher Suite - Operational Manual**

---

## 📋 DAFTAR ISI MODUL
1. [Akses Module](#1-akses-module)
2. [Konsep Kontak](#2-konsep-kontak)
3. [Lihat Daftar Kontak](#3-lihat-daftar-kontak)
4. [Tambah Kontak Manual](#4-tambah-kontak-manual)
5. [Edit Kontak](#5-edit-kontak)
6. [Hapus Kontak](#6-hapus-kontak)
7. [Upload Kontak Bulk (CSV/Excel)](#7-upload-kontak-bulk)
8. [Export Kontak](#8-export-kontak)
9. [Pencarian & Filter](#9-pencarian--filter)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. AKSES MODULE

### 1.1 Via Menu
- **Sidebar** → Pilih **"Manajemen Kontak"**

### 1.2 Via Direct URL
- `http://localhost/email_dispatcher_suite/public/contacts.php`

### 1.3 User Requirements
- **Minimum Role**: User
- **Permission**: Read/Write Contacts
- **Session**: Must be logged in

---

## 2. KONSEP KONTAK

### 2.1 Apa itu Kontak?
**Kontak adalah data individu atau organisation yang akan menerima email.**

Setiap kontak memiliki:
```
┌─ DATA KONTAK ──────────────────────┐
│ ID          : 1234 (Auto)          │
│ Nama        : PT ASAHIMAS          │
│ Email       : supplier@asahimas... │
│ Sumber      : Outlook / Manual     │
│ Tgl Tambah  : 2024-01-15 10:30     │
│ Tgl Update  : 2024-01-20 14:45     │
│ Grup        : Vendor, Supplier     │
│ Status      : Active / Inactive    │
└────────────────────────────────────┘
```

### 2.2 Source (Asal Kontak)
| Source | Arti | Cara Tambah |
|--------|------|-----------|
| **Outlook** | Import dari Microsoft Outlook | Auto-sync atau manual import |
| **Manual** | Input langsung via form | Type di form |
| **Import** | Upload dari file CSV/Excel | Upload file |

### 2.3 Best Practices
✅ **DO:**
- Gunakan email valid (format: name@domain.ext)
- Organize kontak ke dalam grup
- Update kontak secara berkala
- Backup database regularly

❌ **DON'T:**
- Jangan duplikasi kontak yang sama
- Jangan gunakan email invalid
- Jangan hapus kontak tanpa backup
- Jangan gunakan special characters di nama

---

## 3. LIHAT DAFTAR KONTAK

### 3.1 Tampilan Default
Setelah buka Contacts module, muncul table:

```
┌─ MANAJEMEN KONTAK ──────────────────────────────────┐
│ [➕ Tambah] [⬆️ Upload] [Export] [Clear All]        │
│                                                     │
│ [🔍 Cari nama/email...      ]  [Filter ▾]          │
│                                                     │
│ ┌─ DAFTAR KONTAK ────────────────────────────────┐ │
│ │ № │ Nama │ Email │ Sumber │ Grup │ Aksi      │ │
│ ├───┼──────┼─────┼────────┼──────┼──────────┤ │
│ │ 1 │ John │ john@│ Outlook│ Vend │ ✏️ 🗑️  │ │
│ │ 2 │ Jane │ jane@│ Manual │ Cust │ ✏️ 🗑️  │ │
│ │ 3 │ PT AS│ supp│ Import │ Vend │ ✏️ 🗑️  │ │
│ │..│    │      │        │      │        │ │
│ └────────────────────────────────────────────────┘ │
│ [< Prev] [1][2][3]...[100] [Next >]               │
│ Showing 1-50 of 1,234 contacts                    │
└─────────────────────────────────────────────────────┘
```

### 3.2 Kolom Penjelasan

| Kolom | Isi | Fungsi |
|-------|-----|--------|
| **№** | 1, 2, 3, ... | Nomor urut |
| **Nama** | Nama kontak | Clickable untuk detail |
| **Email** | Email address | Primary identifier |
| **Sumber** | Outlook/Manual/Import | Asal data |
| **Grup** | Nama-nama grup | Keanggotaan grup |
| **Aksi** | ✏️ Edit, 🗑️ Hapus | Quick action buttons |

### 3.3 View Detail Kontak
Klik **nama kontak** untuk lihat detail:
```
┌─ DETAIL KONTAK ──────────────────┐
│ Nama: PT ASAHIMAS                │
│ Email: supplier@asahimas.co.id   │
│ Sumber: Outlook                  │
│ Tgl Tambah: 2024-01-15 10:30:45  │
│ Tgl Update: 2024-01-20 14:45:30  │
│ Grup: Vendor, Supplier           │
│                                  │
│ [Edit] [Delete] [Back]           │
└──────────────────────────────────┘
```

### 3.4 Pagination
- **Default limit**: 50 items per page
- **Navigation**: [< Prev] [1][2][3]...[100] [Next >]
- **Go to page**: Input nomor halaman + Enter
- **Change limit**: Dropdown [50 ▾] untuk 10, 25, 50, 100

---

## 4. TAMBAH KONTAK MANUAL

### 4.1 Buka Form Tambah
Klik tombol **"➕ Tambah Kontak"**

Modal popup muncul:
```
┌─ TAMBAH KONTAK BARU ──────────────┐
│ Nama *                             │
│ [________________________]          │
│                                    │
│ Email *                            │
│ [________________________]          │
│                                    │
│ Sumber                             │
│ [Pilih •] Outlook / Manual / Import│
│                                    │
│ [Batal] [💾 Simpan]               │
└────────────────────────────────────┘
```

### 4.2 Isi Field

#### Nama (Required)
- **Tipe**: Text (max 200 karakter)
- **Format**: Boleh nama orang atau organisasi
- **Contoh**:
  - "John Doe"
  - "PT ASAHIMAS"
  - "CV Sejahtera Indonesia"
- **Validasi**: Tidak boleh kosong, tidak boleh special chars

#### Email (Required)
- **Tipe**: Email (format: xxx@yyy.zzz)
- **Validasi**: 
  - ✅ VALID: `john.doe@company.com`
  - ✅ VALID: `supplier@asahimas.co.id`
  - ❌ INVALID: `john@` (missing domain)
  - ❌ INVALID: `john.com` (missing @)
  - ❌ INVALID: `john@@company.com` (double @)

#### Sumber (Optional)
- **Pilihan**:
  - **Outlook**: Dari sync Outlook
  - **Manual**: Input manual (selected by default)
  - **Import**: Dari CSV/Excel import
- **Recommended**: Pilih "Manual" untuk manual input

### 4.3 Simpan
1. Setelah isi informasi, klik **"💾 Simpan"**
2. System validasi:
   - ✅ Email format valid
   - ✅ Nama tidak kosong
   - ✅ Email belum ada (prevent duplicate)
3. Jika valid:
   - ✅ Success message: "Kontak berhasil ditambahkan"
   - ✅ Modal close otomatis
   - ✅ Table refresh, kontak baru muncul di atas
4. Jika invalid:
   - ❌ Error message menampilkan problem
   - Modal tetap terbuka untuk edit

### 4.4 Best Practices
- ✅ Verify email address sebelum simpan
- ✅ Gunakan full name untuk clarity
- ✅ Gunakan format organization yang consistent
- ❌ Jangan pakai "test@test.com" untuk production

---

## 5. EDIT KONTAK

### 5.1 Buka Form Edit
1. Di table, klik tombol **"✏️ Edit"** pada baris kontak
2. Modal popup dengan data pre-filled:
   ```
   ┌─ EDIT KONTAK ────────────────────┐
   │ Nama *                            │
   │ [PT ASAHIMAS     ]                │
   │                                   │
   │ Email *                           │
   │ [supplier@asahimas.co.id]         │
   │                                   │
   │ Sumber                            │
   │ [Outlook         ]                │
   │                                   │
   │ [Batal] [💾 Update]               │
   └───────────────────────────────────┘
   ```

### 5.2 Edit Field
- **Nama**: Ubah nama kontak
- **Email**: Ubah email address
  - ⚠️ WARNING: Jika ubah email, verify yang baru valid
- **Sumber**: Ubah source (tidak recommended)

### 5.3 Simpan Changes
1. Setelah ubah field, klik **"💾 Update"**
2. System validasi lagi
3. Jika valid: ✅ Success message + modal close
4. Jika invalid: ❌ Error message + modal stay open

### 5.4 Undo Changes
- Klik **"Batal"** sebelum update untuk discard changes
- Atau refresh page (Ctrl+R) untuk reload data original

---

## 6. HAPUS KONTAK

### 6.1 Delete Single Contact
1. Klik tombol **"🗑️ Hapus"** pada baris kontak
2. Konfirmasi dialog muncul:
   ```
   ⚠️ HAPUS KONTAK?
   
   Anda akan menghapus kontak:
   "John Doe (john@example.com)"
   
   Tindakan ini TIDAK DAPAT DIBATALKAN!
   
   [Cancel] [Ya, Hapus]
   ```
3. Klik **"Ya, Hapus"** untuk konfirmasi
4. ✅ Kontak terhapus dari database
5. ✅ Table refresh, kontak hilang dari list

### 6.2 Delete Multiple Contacts
1. Di header table, klik **checkbox "Select All"**
2. Atau centang individual checkboxes
3. Klik tombol **"🗑️ Hapus Terpilih"**
4. Konfirmasi: "Hapus 15 kontak?"
5. Klik **"Ya, Hapus"** untuk confirm

### 6.3 Clear All Contacts
⚠️ **WARNING: Destruktive action!**

1. Klik tombol **"Clear All"** (di atas table)
2. Super-konfirmasi dialog:
   ```
   ⚠️⚠️ HAPUS SEMUA KONTAK?
   
   Anda akan MENGHAPUS SEMUA 1,234 kontak
   dalam database.
   
   TINDAKAN INI TIDAK DAPAT DIBATALKAN!
   
   Ketik 'DELETE' untuk konfirmasi:
   [________________]
   
   [Cancel] [Yes, Delete All]
   ```
3. Type "DELETE" untuk unlock button
4. Klik **"Yes, Delete All"**
5. ✅ Semua kontak terhapus

### 6.4 Recovery
- **Before Delete**: Tidak ada cara undo
- **REC OMMENDATION**: Backup database sebelum delete
- **Contact Admin**: Jika delete accidentally, minta restore from backup

---

## 7. UPLOAD KONTAK BULK

### 7.1 Kapan Gunakan Upload
- ✅ Import 10+ kontak sekaligus
- ✅ Migrate dari sistem lain
- ✅ Bulk update kontak
- ✅ Initial setup database

### 7.2 Format File yang Didukung

#### CSV Format
```csv
nama,email,sumber
John Doe,john@example.com,Manual
PT ASAHIMAS,supplier@asahimas.co.id,Outlook
CV Sejahtera,admin@sejahtera.com,Import
Jane Smith,jane@company.com,Manual
```

**Requirements CSV:**
- ✅ Delimiter: comma (,)
- ✅ Header row: nama,email,sumber
- ✅ Encoding: UTF-8
- ✅ Max file size: 10 MB
- ✅ Max rows: 10,000

#### Excel Format (.xlsx)
```
┌──────┬──────────────┬────────────────────────────┬────────┐
│ № │ Nama         │ Email                       │ Sumber │
├──┼──────────────┼────────────────────────────┼────────┤
│ 1│ John Doe     │ john@example.com           │ Manual │
│ 2│ PT ASAHIMAS  │ supplier@asahimas.co.id    │ Outlook│
│ 3│ CV Sejahtera │ admin@sejahtera.com        │ Import │
└──┴──────────────┴────────────────────────────┴────────┘
```

**Requirements Excel:**
- ✅ Sheet name: "contacts" atau "data"
- ✅ Header row: Nama | Email | Sumber
- ✅ Columns order: A=Nama, B=Email, C=Sumber
- ✅ Data start from row 2
- ✅ Max rows: 10,000

### 7.3 Langkah Upload

#### Step 1: Buka Upload Dialog
Klik tombol **"⬆️ Upload Kontak"** (di atas table)

Modal muncul:
```
┌─ UPLOAD KONTAK ─────────────────────┐
│ Pilih file (.csv atau .xlsx):       │
│                                     │
│ [📁 Choose File]  <no file selected>│
│                                     │
│ Preview hasil import:               │
│ [Belum ada file]                    │
│                                     │
│ [Cancel] [Upload]                   │
└─────────────────────────────────────┘
```

#### Step 2: Select File
1. Klik **"📁 Choose File"**
2. File picker dialog muncul
3. Navigate ke file CSV atau Excel
4. Select file
5. Klik **"Open"**
6. Filename muncul di dialog

#### Step 3: Preview
System otomatis preview:
```
┌─ PREVIEW DATA ──────────────────┐
│ Valid Rows: 49 ✅               │
│ Invalid Rows: 1 ⚠️              │
│                                 │
│ ┌──────────────────────────────┐│
│ │ № │ Nama │ Email │ Status   ││
│ ├────────────────────────────┤│
│ │ 1│ John │ john@ │ ⚠️ invalid││
│ │ 2│ Jane │ jane@ │ ✅ OK    ││
│ │ 3│ PT AS│ supp@ │ ✅ OK    ││
│ │...    │      │          ││
│ └──────────────────────────────┘│
│                                 │
│ Klik row invalid untuk lihat error
└─────────────────────────────────┘
```

**Validasi:**
- ✅ Email format valid
- ✅ Nama tidak kosong
- ❌ Duplicate email (warning)
- ❌ Missing required field

#### Step 4: Fix Invalid Rows (Optional)
1. Untuk invalid rows, ada opsi:
   - **Skip row**: Lewati row yang invalid
   - **Edit inline**: Ubah value langsung
2. Atau back to file dan fix di Excel/CSV

#### Step 5: Import
1. Klik **"Upload"** untuk import
2. Progress bar menampilkan:
   ```
   Importing... [████████--] 80%
   Processed: 40/50 rows
   ```
3. After complete:
   ```
   ✅ IMPORT BERHASIL
   
   Summary:
   - Ditambah: 49 kontak baru
   - Diupdate: 0 kontak existing
   - Gagal: 1 kontak (invalid email)
   
   [View Details] [Close]
   ```

### 7.4 Validasi & Error Handling

| Error | Cause | Fix |
|-------|-------|-----|
| "Invalid email format" | Email tidak valid | Check format: name@domain.ext |
| "Duplicate email" | Email sudah ada | Update existing atau skip |
| "Missing required field" | Nama atau email kosong | Fill all required fields |
| "Unsupported file type" | File bukan CSV/Excel | Save as .csv atau .xlsx |
| "File too large" | > 10 MB | Split file jadi smaller |

### 7.5 Best Practices
✅ **DO:**
- Validate data di Excel sebelum upload
- Remove duplicate emails beforehand
- Use consistent format (lowercase email)
- Test upload dengan small sample first

❌ **DON'T:**
- Upload duplicate email yang sudah ada
- Use invalid email format
- Upload with special characters in nama
- Upload file >10 MB

---

## 8. EXPORT KONTAK

### 8.1 Export All Contacts
1. Klik tombol **"📥 Export"** (di atas table)
2. Format selection popup:
   ```
   ┌─ EXPORT KONTAK ──────────────┐
   │ Format:                      │
   │ ⭕ CSV (.csv)               │
   │ ⭕ Excel (.xlsx)            │
   │ ⭕ JSON (.json)             │
   │                              │
   │ Include:                     │
   │ [✓] Nama                    │
   │ [✓] Email                   │
   │ [✓] Sumber                  │
   │ [✓] Tgl Tambah              │
   │ [✓] Grup                    │
   │                              │
   │ [Cancel] [Export]            │
   └──────────────────────────────┘
   ```
3. Pilih format (CSV recommended)
4. Check kolom yang ingin di-include
5. Klik **"Export"**
6. File auto-download ke komputer

### 8.2 Export Filtered/Selected
1. Apply filter (search, sumber, dll)
2. Select contacts (checkbox)
3. Klik **"Export Selected"**
4. Hanya selected contacts yang di-export

### 8.3 File Output

**CSV Format:**
```csv
nama,email,sumber,tgl_tambah,grup
John Doe,john@example.com,Manual,2024-01-15,Vendor;Customer
PT ASAHIMAS,supplier@asahimas.co.id,Outlook,2024-01-10,Vendor
```

**Excel Format:**
```
Sheet: "Contacts"
Columns: Nama | Email | Sumber | Tgl_Tambah | Grup
Rows: All data
```

---

## 9. PENCARIAN & FILTER

### 9.1 Search by Nama/Email
1. Di field **"🔍 Cari nama/email"**, type keyword
2. Table otomatis filter real-time:
   ```
   [🔍 asahi        ]
   
   Results: 3 matched
   - PT ASAHIMAS (supplier@asahimas.co.id)
   - ASAHI Corp (info@asahicorp.com)
   - PT ASAHI Indonesia (contact@asahiindonesia.com)
   ```
3. Clear search + Enter untuk reset

### 9.2 Filter by Sumber
1. Klik dropdown **"Filter ▾"**
2. Pilih sumber:
   ```
   ⭕ All (default)
   ⭕ Outlook
   ⭕ Manual
   ⭕ Import
   ```
3. Table filter sesuai pilihan
4. Pilih "All" untuk reset

### 9.3 Sort by Column
1. Klik header kolom untuk sort
2. First click: Sort ascending (A-Z)
3. Second click: Sort descending (Z-A)
4. Indicator: ↑ (asc) atau ↓ (desc)

**Contoh:**
- Click "Nama" → Sort by name A-Z
- Click "Email" → Sort by email A-Z
- Click "Tgl Tambah" → Sort by date newest first

### 9.4 Advanced Filter
Untuk complex filter, gunakan kombinasi:
1. **Search**: Nama contains "john"
2. **Sumber**: Filter = "Manual"
3. **Sort**: By email A-Z

Result: Manual contacts dengan "john" di nama, sorted by email

---

## 10. TROUBLESHOOTING

### Problem 10.1: Duplicate Contacts
**Issue:** Kontak yang sama muncul berkali-kali

**Cause:**
- Import dari multiple source (Outlook + Manual)
- Typo saat add manual

**Solution:**
1. Use exported data untuk identify duplicates
2. Compare email addresses
3. Manually delete duplicate records
4. Or use admin tools untuk auto remove duplicates

### Problem 10.2: Invalid Email Not Caught
**Issue:** Email invalid but saved in database

**Cause:**
- Upload validation skip
- Special database operation

**Solution:**
1. Edit contact, fix email
2. Or delete dan re-add
3. Test email format: name@domain.ext

### Problem 10.3: Upload Stuck/Timeout
**Issue:** Upload file besar hang/timeout

**Cause:**
- File terlalu besar (>10MB)
- Network issue
- Browser timeout

**Solution:**
1. Split file into smaller chunks
2. Check file size: Max 10 MB
3. Try different browser
4. Contact IT jika persistent issue

### Problem 10.4: Search Not Working
**Issue:** Search return no result

**Cause:**
- Typo di search keyword
- Case sensitivity issue
- Kontak memang tidak ada

**Solution:**
1. Check spelling of search term
2. Try partial keyword (misal: "asahi" instead of "asahimas")
3. Clear search + verify kontak ada di table
4. Refresh browser (Ctrl+F5)

### Problem 10.5: Cannot Edit Email
**Issue:** Email field read-only saat edit

**Cause:**
- Database constraint
- Permission issue

**Solution:**
1. Delete kontact dan add baru
2. Contact admin for release constraint

---

## 📞 QUICK SUPPORT
- **Error saat upload**: Check CSV format & file size
- **List too long**: Use search/filter untuk narrow down
- **Need bulk operation**: Contact IT untuk custom script
- **Backup data**: Export as CSV for safekeeping

---

**END OF MODULE 1**

*Untuk modul lainnya, buka MANUAL_OPERASIONAL.md*
