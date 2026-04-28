# 📧 MANUAL OPERASIONAL EMAIL DISPATCHER SUITE
**Versi: 1.0 | Tanggal: March 5, 2026 | Bahasa: Indonesia**

---

## 📑 DAFTAR ISI
1. [Panduan Login & Autentikasi](#1-panduan-login--autentikasi)
2. [Dashboard & Overview](#2-dashboard--overview)
3. [Manajemen Kontak](#3-manajemen-kontak)
4. [Setup Avatar & Profile](#4-setup-avatar--profile)
5. [Membuat Grup Kontak](#5-membuat-grup-kontak)
6. [Membuat Group Order](#6-membuat-group-order)
7. [Upload Email Template](#7-upload-email-template)
8. [Compose Email](#8-compose-email)
9. [Preview & Similarity Matching](#9-preview--similarity-matching)
10. [Send Email](#10-send-email)
11. [Lihat Log & History](#11-lihat-log--history)
12. [Settings & Konfigurasi](#12-settings--konfigurasi)
13. [Troubleshooting & FAQ](#13-troubleshooting--faq)

---

## 1. PANDUAN LOGIN & AUTENTIKASI

### 1.1 Akses Aplikasi
- **URL**: `http://localhost/email_dispatcher_suite/public/login.php`
- **Browser**: Chrome, Firefox, Safari (minimum IE 11)

### 1.2 Proses Login
1. Masuk ke halaman login
2. Input **Username**: username Anda
3. Input **Password**: password Anda
4. Klik tombol **"Login"**

### 1.3 Login Berhasil
- ✅ Redirect ke halaman Dashboard (index.php)
- ✅ Top-right menampilkan: username + avatar
- ✅ Sidebar aktif dengan menu

### 1.4 Logout
1. Klik **Avatar/Username** di top-right
2. Pilih **"Logout"**
3. Redirect ke halaman login

### 1.5 Troubleshooting Login
| Masalah | Solusi |
|---------|--------|
| "Username/Password salah" | Periksa kembali kredensial. Pastikan Caps Lock OFF |
| "Session expired" | Silakan login kembali |
| "Page not found" | Pastikan URL benar: .../public/login.php |

---

## 2. DASHBOARD & OVERVIEW

### 2.1 Akses Dashboard
- **URL**: `http://localhost/email_dispatcher_suite/public/index.php`
- **Otomatis terbuka** setelah login berhasil

### 2.2 Informasi yang Ditampilkan
```
┌─ STATISTIK RINGKAS ────────────────┐
│ • Total Kontak: 1,234              │
│ • Total Grup: 12                   │
│ • Email Terkirim (Hari ini): 456   │
│ • Sukses Rate: 98.5%               │
│ • Failed Email: 7                  │
│ • Average Response Time: 2.3s      │
└────────────────────────────────────┘
```

### 2.3 Fitur Dashboard
- **Last Sent Emails**: Tampilkan 10 email terakhir dengan status
- **Daily Statistics**: Grafik pengiriman per hari
- **Quick Actions**: Links cepat ke modul utama
  - ➕ Add Contact
  - 📧 Create Email
  - 📊 View Logs
  - ⚙️ Settings

### 2.4 Real-time Updates
- Dashboard auto-refresh setiap **30 detik**
- Statistik selalu up-to-date

---

## 3. MANAJEMEN KONTAK

### 3.1 Akses Module Kontak
- **Menu**: Sidebar → "Manajemen Kontak"
- **Direct URL**: `.../public/contacts.php`

### 3.2 Daftar Kontak (View)
**Halaman utama menampilkan:**
- Table dengan kolom: No | Nama | Email | Sumber | Grup | Aksi
- **Search**: Filter kontak by nama/email
- **Sort**: Klik header kolom untuk sort
- **Pagination**: Navigation per 50 items

### 3.3 Tambah Kontak Manual
1. Klik tombol **"➕ Tambah Kontak"**
2. Form popup muncul:
   ```
   Nama      : [________] (required)
   Email     : [________] (required, valid email)
   Sumber    : [Outlook/Manual/Import] (dropdown)
   ```
3. Klik **"Simpan"**
4. Success message: ✅ Kontak berhasil ditambahkan

### 3.4 Edit Kontak
1. Klik tombol **"✏️ Edit"** di baris kontak
2. Form popup dengan data pre-filled
3. Ubah field yang diperlukan
4. Klik **"Simpan"**

### 3.5 Hapus Kontak
1. Klik tombol **"🗑️ Hapus"** di baris kontak
2. Konfirmasi popup:
   ```
   ⚠️ Anda akan menghapus kontak "John Doe (john@example.com)"
   Tindakan ini TIDAK DAPAT DIBATALKAN!
   ```
3. Klik **"Ya, Hapus"** untuk konfirmasi
4. Kontak terhapus dari database

### 3.6 Upload Kontak (Bulk Import)
**Fitur untuk import kontak massal dari file:**

#### 3.6.1 Format File yang Didukung
- **CSV**: `nama,email,sumber`
- **Excel (.xlsx)**: Kolom: Nama | Email | Sumber

#### 3.6.2 Langkah Upload
1. Klik tombol **"⬆️ Upload Kontak"**
2. Select file dari komputer
3. System preview data:
   ```
   Preview: 50 baris pertama
   ✓ Valid: 49 kontak
   ✗ Invalid: 1 kontak (missing email)
   ```
4. Klik **"Import"** untuk simpan
5. Success message dengan summary:
   ```
   ✅ Berhasil: 49 kontak
   ⚠️ Gagal: 1 kontak
   ```

#### 3.6.3 CSV Format Template
```csv
nama,email,sumber
PT Asahimas,supplier@asahimas.co.id,Outlook
CV Sejahtera,admin@sejahtera.com,Manual
PT Handal,info@handal.biz,Import
```

---

## 4. SETUP AVATAR & PROFILE

### 4.1 Akses Setup Avatar
- **Menu**: Sidebar → "Setup Avatar"
- **Direct URL**: `.../public/setup_avatar.php`

### 4.2 Apa itu Avatar?
- **Foto profil sender** untuk digunakan di email template
- Digunakan untuk memberikan kesan profesional
- Dapat di-customize per email campaign

### 4.3 Upload Avatar
1. Klik **"📸 Pilih Foto Avatar"**
2. Select foto dari komputer
   - **Format**: JPG, PNG, GIF
   - **Ukuran**: Max 2MB
   - **Rekomendasi**: 200x200px
3. Preview foto tampil di modal
4. Klik **"✅ Simpan Avatar"**
5. Success: Avatar tersimpan

### 4.4 Edit Avatar
1. Klik tombol **"✏️ Edit"** di avatar card
2. Upload foto baru atau hapus
3. Klik **"Simpan"**

### 4.5 Kelola Profile Account
**Field yang dapat di-edit:**
- **Display Name**: Nama yang muncul di email
- **Email Signature**: Tanda tangan email
- **Department**: Departemen/Divisi
- **Phone**: No telepon (optional)

#### Langkah Edit Profile:
1. Klik tombol **"⚙️ Edit Profile"**
2. Form muncul dengan data saat ini
3. Update field yang diinginkan
4. Klik **"💾 Simpan"**

---

## 5. MEMBUAT GRUP KONTAK

### 5.1 Apa itu Grup?
- **Kumpulan kontak** yang dikelompokkan berdasarkan kategori
- Contoh: Grup "Supplier ASAHIMAS", "Customer B2B", dll
- **Keuntungan**: Kirim email ke banyak orang sekaligus dengan mudah

### 5.2 Akses Management Grup
- **Module**: Contacts (`.../public/contacts.php`)
- **Tab**: "Kelola Grup" (di bawah tab Kontak)

### 5.3 Buat Grup Baru
1. Klik tombol **"➕ Buat Grup Baru"**
2. Modal drawer terbuka dari sisi kanan:
   ```
   ┌─ Kelola Anggota Grup ──┐
   │ Nama Grup              │
   │ [Vendor ASAHIMAS    ] │
   │                        │
   │ [🔍 Cari Kontak... ]  │
   │ [✓ Pilih Semua]       │
   │ [✗ Bersihkan]         │
   │                        │
   │ Dipilih: 12            │
   │                        │
   │ [checkbox] John Doe    │
   │ [checkbox] Jane Smith  │
   │ [checkbox] PT ABS      │
   │ ...                    │
   └─ [Batal] [Simpan Grup]─┘
   ```
3. **Input nama grup** (wajib)
4. **Cari & pilih kontak** yang ingin dimasukkan:
   - Gunakan search box untuk filter
   - Centang checkbox tiap kontak
   - Atau klik "Pilih Semua" untuk semua kontak
5. **Lihat counter** "Dipilih: X"
6. Klik **"Simpan Grup"**
7. Success: Grup berhasil dibuat

### 5.4 Edit Nama / Anggota Grup
1. Di tab "Kelola Grup", klik tombol **"✏️ Edit"** pada grup
2. Modal terbuka dengan:
   - Nama grup dapat diubah
   - Centang ulang kontak yang ingin diinclude
3. Ubah nama atau member list
4. Klik **"Simpan Grup"**
5. Success message: ✅ Grup berhasil diperbarui

### 5.5 Hapus Grup
1. Klik tombol **"🗑️ Hapus"** pada grup
2. Konfirmasi popup:
   ```
   ⚠️ Hapus Grup?
   Anda akan menghapus grup "Vendor ASAHIMAS" dan semua anggotanya
   Tindakan ini TIDAK DAPAT DIBATALKAN!
   ```
3. Klik **"Ya, Hapus"** untuk konfirmasi
4. Grup & anggotanya terhapus

### 5.6 View Anggota Grup
1. Klik tombol **"👥 Lihat Anggota"** pada grup
2. Popup menampilkan:
   ```
   ┌─ Anggota Grup: Vendor ASAHIMAS ─┐
   │ 1. John Doe (john@mail.com)      │
   │ 2. Jane Smith (jane@mail.com)    │
   │ 3. PT ASAHIMAS (supplier@as.com) │
   │ 4. ....                          │
   └──────────────────────────────────┘
   ```

---

## 6. MEMBUAT GROUP ORDER

### 6.1 Apa itu Group Order?
- **Koleksi grup** untuk kampanye email yang specific
- Contoh: "Q1 2024 Sales Campaign" berisi 3 grup (Supplier, Partner, Customer)
- **Keuntungan**: Organize multiple grup untuk 1 campaign

### 6.2 Akses Management Group Order
- **Module**: Contacts (`.../public/contacts.php`)
- **Tab**: "Kelola Group Order"

### 6.3 Buat Group Order Baru
1. Klik **"➕ Buat Group Order Baru"**
2. Modal drawer terbuka:
   ```
   ┌─ Kelola Group Order ──────┐
   │ Nama Group Order          │
   │ [Q1 2024 Sales Campaign ] │
   │                           │
   │ [🔍 Cari Grup...        ] │
   │ [✓ Pilih Semua]          │
   │ [✗ Bersihkan]            │
   │                           │
   │ Dipilih: 3                │
   │                           │
   │ [checkbox] Vendor         │
   │ [checkbox] Partner        │
   │ [checkbox] Customer       │
   │ ...                       │
   └─ [Batal] [Simpan]────────┘
   ```
3. **Input nama group order** (wajib)
4. **Pilih grup yang ingin dimasukkan:**
   - List semua grup yang tersedia
   - Centang grup untuk include
   - Min. 1 grup harus dipilih
5. Klik **"Simpan Group Order"**
6. Success: Group Order berhasil dibuat

### 6.4 Edit Group Order
1. Klik **"✏️ Edit"** pada group order
2. Modal terbuka dengan data pre-filled
3. Ubah nama atau pilihan grup
4. Klik **"Simpan"**

### 6.5 Hapus Group Order
1. Klik **"🗑️ Hapus"** pada group order
2. Konfirmasi popup
3. Klik **"Ya, Hapus"**

### 6.6 Quick Shortcut
- **Saat compose email**: Langsung select group order
- Semua grup dalam order otomatis di-load
- Recipients auto-populated

---

## 7. UPLOAD EMAIL TEMPLATE

### 7.1 Akses Upload Template
- **Module**: Compose Email (`.../public/compose.php`)
- **Section**: "📎 Lampiran & Template"

### 7.2 Format File Template yang Didukung
| Format | Tipe | Catatan |
|--------|------|---------|
| PDF | Document | Untuk attachment |
| XLSX | Spreadsheet | Excel data |
| DOC/DOCX | Document | Microsoft Word |
| TXT | Text | Plain text |
| CSV | Data | Comma-separated values |
| JPG/PNG | Image | Untuk inline images |
| ZIP | Archive | Multiple files |

### 7.3 Langkah Upload Template
1. Di section "Lampiran & Template", klik **"📤 Pilih File"**
2. Select 1 atau lebih file dari komputer
3. System menampilkan preview:
   ```
   Upload Progress:
   ✅ sales_template.pdf (2.3 MB)
   ✅ price_list.xlsx (1.5 MB)
   ⏳ brochure.zip (5.2 MB) - uploading...
   ```
4. Setelah semua selesai:
   ```
   ✅ 3 file berhasil di-upload
   ```
5. File muncul di list dengan tombol:
   - **👁️ Preview**: Lihat file
   - **🗑️ Hapus**: Hapus file

### 7.4 Clear Semua Lampiran
1. Klik tombol **"🗑️ Hapus Semua Lampiran"**
2. Konfirmasi popup
3. Semua file terhapus

### 7.5 Best Practices
- **Upload sebelum compose**: Sudah siap saat compose
- **Naming convention**: Gunakan nama file yang descriptive
- **File size**: Max 10MB per file
- **Reuse**: File dapat digunakan untuk multiple emails

---

## 8. COMPOSE EMAIL

### 8.1 Akses Compose Email
- **Menu**: Sidebar → "Kirim Email (Similarity)"
- **Direct URL**: `.../public/compose.php`

### 8.2 Form Email Composer
```
┌─ FORM COMPOSE EMAIL ──────────────────┐
│                                        │
│ Subjek: [Subject Email Template     ] │
│                                        │
│ Email CC: [cc@example.com           ] │
│                                        │
│ ┌─ BODY EMAIL (Rich Editor) ────────┐ │
│ │ [B] [I] [U] [Link] [Image] [Table]│ │
│ │                                    │ │
│ │ Konten email dengan formatting...  │ │
│ │                                    │ │
│ └────────────────────────────────────┘ │
│                                        │
│ Filter Grup:  [0 GRUP DIPILIH ▾]     │
│ Cari Kontak: [search box          ]   │
│                                        │
│ Dipilih: 0 / Total: 1,234             │
│ [Slider Contact] [Preview]            │
│                                        │
│ [Lampiran] [Upload] [Clear]          │
│                                        │
│ [Batal] [Preview & Cocokkan]         │
└────────────────────────────────────────┘
```

### 8.3 Cara Menggunakan - Langkah per Langkah

#### Step 1: Isi Subjek Email
1. Klik field "Subjek"
2. Input subjek email (required)
3. Contoh: "Penawaran Produk Terbaru - Q1 2024"

#### Step 2: Isi Email CC (Optional)
1. Klik field "Email CC"
2. Input email yang ingin di-CC (pisahkan dengan semicolon jika multiple)
3. Contoh: `director@company.com;manager@company.com`

#### Step 3: Compose Email Body
1. Klik area white editor (rich text editor dengan Quill)
2. Gunakan toolbar formatting:
   - **B** = Bold
   - **I** = Italic
   - **U** = Underline
   - **Link** = Insert hyperlink
   - **Image** = Insert gambar
   - **Table** = Insert tabel
3. Paste atau ketik konten email
4. Support HTML copy-paste dari Word/template

#### Step 4: Pilih Recipients
**Option A: Select by Grup**
1. Klik dropdown **"Filter Grup"**
2. Panel terbuka dengan list semua grup + group order
3. **Manual Grup**: Centang 1 atau lebih grup
   - Contoh: Vendor ASAHIMAS + Customer B2B
   - System otomatis load recipients dari grup
4. **Group Order**: Centang group order
   - Contoh: "Q1 2024 Campaign"
   - Semua grup dalam order otomatis di-load
5. Badge menampilkan "2 GRUP DIPILIH ✕"

**Option B: Manual Select Recipients**
1. Jika tidak ada grup dipilih, gunakan manual selection
2. Search kontak di field "Cari Kontak"
3. Centang checkbox untuk select recipients
4. Counter menampilkan "Dipilih X / Total 1,234"

#### Step 5: Upload Lampiran (Optional)
1. Scroll ke section **"📎 Lampiran & Template"**
2. Klik **"📤 Pilih File"**
3. Select file dari komputer
4. File muncul di list:
   ```
   ✅ template.pdf (2.3 MB) [Show] [Delete]
   ✅ price_list.xlsx (1.5 MB) [Show] [Delete]
   ```
5. Untuk clear semua: Klik **"🗑️ Hapus Semua"**

#### Step 6: Preview & Edit
1. Sebelum submit, klik tombol **"👁️ Preview"** (optional)
2. Review email preview

### 8.4 Validasi Form
Sebelum melanjutkan, pastikan:
- ✅ **Subjek**: Filled
- ✅ **Body**: Filled
- ✅ **Recipients**: Min. 1 kontak dipilih
- ✅ **Attachment**: Optional (tapi recommended)

### 8.5 Submit
Klik tombol **"3) Preview & Cocokkan (Similarity)"**
→ Redirect ke halaman Match Preview

---

## 9. PREVIEW & SIMILARITY MATCHING

### 9.1 Apa itu Similarity Matching?
- **System cek kecocokan** antara nama file attachment dan recipient
- Misalnya: File "ASAHIMAS_price.pdf" diprioritaskan ke recipient di grup "Supplier ASAHIMAS"
- **Scoring**: 0-100, threshold default 60

### 9.2 Halaman Match Preview
```
┌─ PREVIEW PENCOCOKAN ──────────────────┐
│ Subjek: Penawaran Produk Q1 2024      │
│ Email CC: director@company.com        │
│                                        │
│ Threshold Similarity: [60 ▭▭▭▭■] 80  │
│                                        │
│ ┌─ RENCANA PENGIRIMAN ──────────────┐ │
│ │ Penerima | Grup | Email | Attach  │ │
│ │──────────────────────────────────│ │
│ │ John Doe │      │       │ ✓.pdf  │ │
│ │ PT ABS   │ Vend │ email │ ✓.pdf  │ │
│ │ Jane     │      │       │ ⚠️ low │ │
│ └──────────────────────────────────┘ │
│                                        │
│ [⟵ Back] [→ Send Email]               │
└────────────────────────────────────────┘
```

### 9.3 Cara Baca Tabel Preview

| Kolom | Isi | Keterangan |
|-------|-----|-----------|
| **Penerima** | Nama recipient | Primary TO field |
| **Grup** | Nama grup | Jika dari grup selection |
| **Email** | Email address | Target email |
| **Lampiran** | Filename | Attachment yang akan dikirim |
| **Skor** | 0-100 | Similarity score |

### 9.4 Status Lampiran
| Icon | Makna | Aksi |
|------|-------|------|
| ✅ filename.pdf | Match ≥ Threshold | Akan dikirim |
| ⚠️ Low Score | Match < Threshold | Tidak akan dikirim |
| ❌ No Match | No suitable attachment | Kirim tanpa attachment |

### 9.5 Adjust Similarity Threshold
1. **Slider di atas tabel** untuk adjust threshold
2. Drag slider left/right untuk ubah nilai
3. Range: 0-100
4. Default: 60 (recommended)
5. Setiap kali ubah, tabel otomatis update

### 9.6 Interpretasi Skor
- **85-100**: ✅ Sangat cocok (pasti kirim)
- **70-84**: ✅ Cocok (kirim)
- **60-69**: ⚠️ Sesuai threshold (kirim jika terpenuhi)
- **<60**: ❌ Tidak cocok (skip)

### 9.7 Lihat Detail Matching Logic
- Klik pada row untuk expand dan lihat:
  ```
  Recipient: John Doe (john@mail.com)
  Grup: Supplier ASAHIMAS
  
  Filename: ASAHIMAS_price.pdf
  Match Logic:
  - Email match: 45%
  - Name match: 72%
  - Group match: 95% ← BEST
  Final Score: 95 ✅
  ```

### 9.8 Actions
- **[⟵ Back]**: Kembali ke compose, edit email
- **[→ Send Email]**: Lanjut ke tahap pengiriman

---

## 10. SEND EMAIL

### 10.1 Process Sending
Setelah klik "Send Email" dari preview:

**Flow:**
```
1. Validate email data
2. Create job entry in database
3. Prepare JSON job file
4. Execute PowerShell script (send_outlook_emails.ps1)
5. Monitor sending progress
6. Update status per recipient
7. Display final report
```

### 10.2 Sending Progress
**Halaman menampilkan:**
```
┌─ Pengiriman Diproses ─────────────────┐
│                                        │
│ Job ID: 12345                         │
│                                        │
│ Status: Processing...                 │
│                                        │
│ PowerShell Output:                    │
│ [OK] Processing 26 items from job...  │
│ [OK] [1/26] Processing item 1         │
│ [OK] Email dikirim. To: john@example  │
│ [OK] [2/26] Processing item 2         │
│ [OK] Email dikirim. To: jane@example  │
│ ...                                    │
│                                        │
│ [Lihat Rekap & Log]                   │
└────────────────────────────────────────┘
```

### 10.3 Apa yang Terjadi
1. **Validation**: Cek subject, body, recipients, attachment
2. **Database**: Insert mail_jobs & mail_job_items records
3. **JSON Generation**: Buat file job_X.json di temp/
4. **PowerShell Execute**: Jalankan send_outlook_emails.ps1
   - Connect ke Outlook
   - Resolve account/sender
   - Kirim email per item (atau grouped by recipients)
   - Generate result_job_X.json
5. **Update Database**: Update status each item (sent/failed)
6. **Display Result**: Show success/failure summary

### 10.4 Success Indicators
- ✅ All items status = "sent"
- ✅ PowerShell script exit code = 0
- ✅ Result JSON file tersimpan
- ✅ Database records updated

### 10.5 Failure Handling
| Error | Cause | Solution |
|-------|-------|----------|
| Outlook not connected | Outlook service tidak berjalan | Start Outlook application |
| Invalid email address | Format email salah | Back to compose, fix email |
| Account not found | Sender account tidak ada | Check account setting |
| Attachment not found | File sudah dihapus | Re-upload lampiran |
| Subject/Body missing | Form tidak lengkap | Back to compose |

### 10.6 Re-send Failed Emails
1. Dari logs (section 11), click failed job
2. View detail & see failed items
3. Klik button "Re-send Failed"
4. System akan kirim ulang hanya gagal items
5. New job ID created

---

## 11. LIHAT LOG & HISTORY

### 11.1 Akses Logs Module
- **Menu**: Sidebar → "Rekap Email"
- **Direct URL**: `.../public/logs.php`

### 11.2 Halaman Logs
```
┌─ REKAP EMAIL & LOG PENGIRIMAN ─────────┐
│                                         │
│ Filter: [Status ▾] [Tanggal ▾] [Search]│
│                                         │
│ ┌─ DAFTAR EMAIL JOBS ────────────────┐ │
│ │ ID│ Tgl Kirim│ Status │ Sukses│ Ggl│ │
│ │──────────────────────────────────│ │
│ │12│ 2024-01-15 │ Completed │ 26 │ 0 │ │
│ │11│ 2024-01-14 │ Completed │ 18 │ 2 │ │
│ │10│ 2024-01-13 │ Failed │ 12 │ 5 │ │
│ │ 9│ 2024-01-13 │ Processing│ 15 │ - │ │
│ └────────────────────────────────────┘ │
│                                         │
│ [< Prev] [1] [2] [3] [Next >]          │
└─────────────────────────────────────────┘
```

### 11.3 Kolom Informasi
| Kolom | Keterangan |
|-------|-----------|
| **ID** | Job ID (click untuk detail) |
| **Tgl Kirim** | Tanggal & jam pengiriman |
| **Status** | Completed / Failed / Processing |
| **Nama Campaign** | Email subject |
| **Sukses** | Jumlah email berhasil |
| **Gagal** | Jumlah email gagal |
| **Total** | Total recipients |

### 11.4 Filter & Search
1. **Status Filter**: Dropdown pilih Completed / Failed / Processing
2. **Date Filter**: Range picker untuk pilih tanggal
3. **Search**: Cari by subject/campaign name

### 11.5 View Job Detail
Klik pada ID job untuk melihat:
```
┌─ JOB DETAIL #12 ──────────────────────┐
│ Subjek: Penawaran Q1 2024             │
│ Tanggal: 2024-01-15 10:30:45          │
│ Total Recipients: 26                  │
│ Status: Completed                     │
│ Sukses: 26 ✅                         │
│ Gagal: 0                              │
│                                        │
│ CC: director@company.com              │
│                                        │
│ ┌─ RECIPIENTS ──────────────────────┐ │
│ │ Email │ Status │ Time │ Message   │ │
│ │─────────────────────────────────│ │
│ │john@..│ Sent   │ 10:30│ OK       │ │
│ │jane@..│ Sent   │ 10:31│ OK       │ │
│ │...    │        │      │          │ │
│ └───────────────────────────────────┘ │
│                                        │
│ [Download Report] [Re-send Failed]    │
└────────────────────────────────────────┘
```

### 11.6 Download Report
1. Klik **"📥 Download Report"**
2. File Excel (.xlsx) di-generate dengan:
   - Summary: Total, Sukses, Gagal
   - Detail table: Semua recipient + status
   - Timestamp: Generated at

### 11.7 Re-send Failed Items
1. Jika ada failed items, button **"🔄 Re-send Failed"** aktif
2. Klik untuk kirim ulang failed recipients
3. System create job baru dengan updated data
4. Default threshold = last threshold

### 11.8 Statistics & Analytics
- **Top performers**: Campaigns dengan success rate tertinggi
- **Common errors**: Error messages terbanyak
- **Timing analysis**: Peak sending hours
- **Export data**: For analysis di Excel

---

## 12. SETTINGS & KONFIGURASI

### 12.1 Akses Settings
- **Menu**: Sidebar → "Pengaturan"
- **Direct URL**: `.../public/settings.php`
- **Requires**: Admin/Manager role

### 12.2 Pengaturan Aplikasi

#### 12.2.1 Email Settings
```
┌─ PENGATURAN EMAIL ────────────┐
│ Server SMTP: [empty]          │
│ Port: [25] ▭                  │
│ Username: [optional]          │
│ Password: [••••••••••]         │
│ Encryption: [None ▾]          │
│ From Address: [default]       │
│ [Test Connection]             │
└───────────────────────────────┘
```

**Langkah:**
1. Input SMTP server hostname
2. Set port (default: 25, 465, 587)
3. Set username/password (if needed)
4. Choose encryption (None/TLS/SSL)
5. Set default From address
6. Klik **"Test Connection"**
7. Success: ✅ Connection OK

#### 12.2.2 Outlook Integration
```
┌─ OUTLOOK INTEGRATION ─────────┐
│ Account Email: [             ] │
│ [Connect to Outlook]          │
│ Status: Connected ✅          │
│ [Disconnect]                  │
│                               │
│ Auto Sync Contacts: [✓]       │
│ Sync Interval: [30 min ▾]     │
│                               │
│ [Manual Sync Now]             │
└───────────────────────────────┘
```

**Langkah Setup:**
1. Klik **"Connect to Outlook"**
2. Microsoft login popup muncul
3. Sign in dengan account Outlook
4. Grant permission untuk aplikasi
5. Success: Account connected
6. Enable "Auto Sync" untuk auto-update contacts

#### 12.2.3 Similarity Matching
```
┌─ SIMILARITY SETTINGS ─────────┐
│ Default Threshold: [60 ▭▭■]   │
│ Matching Algorithm: [Fuzzy ▾] │
│                               │
│ Boost Group Match: [✓]        │
│ Boost Substring: [✓]          │
│                               │
│ [Save Settings]               │
└───────────────────────────────┘
```

**Opsi:**
- **Threshold**: Default untuk preview (0-100)
- **Algorithm**: Fuzzy (recommended) / Exact
- **Boost Group**: Extra weight for group name match
- **Boost Substring**: Extra weight for substring match

#### 12.2.4 Notification Settings
```
┌─ NOTIFIKASI ──────────────────┐
│ Email Notifications: [✓]      │
│ Send to: [email@company.com ] │
│                               │
│ Notify on Success: [✓]        │
│ Notify on Failure: [✓]        │
│ Notify on Delay: [✓]          │
│                               │
│ Daily Report: [✓] [8:00 AM]   │
│                               │
│ [Save]                        │
└───────────────────────────────┘
```

#### 12.2.5 Security & Access Control
```
┌─ SECURITY SETTINGS ───────────┐
│ Session Timeout: [60 min ▾]   │
│ Max Login Attempts: [5 ▾]     │
│ Password Policy: [Strong ▾]   │
│                               │
│ API Keys:                     │
│ [New API Key]                 │
│ ak_xxxxx... [Copy] [Revoke]   │
│                               │
│ Audit Log: [View]             │
│ [Clear Audit Log]             │
│                               │
│ [Save]                        │
└───────────────────────────────┘
```

### 12.3 Admin Only Settings

#### 12.3.1 User Management
```
┌─ MANAJEMEN USER ──────────────┐
│ [➕ Add User] [Import Users]   │
│                               │
│ Users List:                   │
│ [Nama] [Email] [Role] [Status]│
│ John Doe | john@... │ Admin │✓ │
│ Jane Smith | jane@..│ User  │✓ │
│ ...                           │
│                               │
│ [Edit] [Disable] [Delete]     │
└───────────────────────────────┘
```

#### 12.3.2 Database Maintenance
```
┌─ DATABASE MAINTENANCE ────────┐
│ Backup Database: [Backup Now] │
│ Last backup: 2024-01-15 22:30 │
│                               │
│ Clear Old Logs: [>1 year]     │
│ Count: 1,234 records          │
│ [Clear Now] [Schedule]        │
│                               │
│ Optimize Tables: [Run]        │
│ Status: OK ✅                 │
│                               │
│ Database Size: 245 MB         │
└───────────────────────────────┘
```

---

## 13. TROUBLESHOOTING & FAQ

### 13.1 Email Tidak Terkirim

**Problem: Email tidak dikirim setelah klik Send**

**Checklist:**
1. ✅ Lihat halaman logs, apakah job created?
2. ✅ Status job ada apa? (Completed, Failed, Processing)
3. ✅ Outlook application aktif/berjalan?
4. ✅ Sender account terkonfigurasi di Outlook?
5. ✅ Recipient email valid? (check format)
6. ✅ Attachment file masih ada?

**Solusi:**
- **Jika Outlook tidak aktif**: Start Outlook aplikasi di Windows
- **Jika account tidak cocok**: Settings → Outlook Integration → reconnect
- **Jika attachment missing**: Re-upload di compose page
- **Jika persistent**: Contact IT / Check error log di browser console

### 13.2 Similarity Score Terlalu Rendah

**Problem: Attachment match score < threshold, tidak dikirim**

**Cause:**
- File name tidak mirip dengan recipient name/group
- Contoh: File "promo_general.pdf" vs Grup "Supplier ASAHIMAS"

**Solusi:**
1. **Rename file** lebih sesuai dengan recipient
   - Contoh: "ASAHIMAS_promo.pdf"
   - Format: `[GroupName]_[content].pdf`

2. **Lower threshold** di preview page
   - Drag slider to 30-40 untuk lebih permissive
   - Tapi risk = wrong attachment sent

3. **Use Group Order** untuk clear organization
   - Organize file per group
   - Name file per group natively

### 13.3 Duplicate Contacts

**Problem: Kontak yang sama muncul berkali-kali**

**Cause:**
- Import dari multiple sources (Outlook + Manual + Import)
- Data duplicate di database

**Solusi:**
1. Setup → Database Maintenance → "Remove Duplicates"
2. Or manual: Contacts → Delete duplicate records
3. Configure auto-sync untuk single source of truth

### 13.4 Group Tidak Muncul di Compose

**Problem: Dibuat grup, tapi tidak muncul saat compose**

**Cause:**
- Grup belum tersimpan
- Browser cache issue
- Group memerlukan minimal 1 member

**Solusi:**
1. Refresh browser (Ctrl+F5)
2. Ensure group ada member ≥ 1
3. Clear browser cache
4. Cek di Contacts → verify grup ada

### 13.5 Avatar Not Showing

**Problem: Avatar tidak muncul di email**

**Cause:**
- Avatar belum upload
- Email template tidak include avatar tag
- Image file corrupted

**Solusi:**
1. Setup Avatar → upload new photo
2. Di email body, use `<img>` tag:
   ```html
   <img src="[avatar_url]" width="100" alt="Author">
   ```
3. Or contact admin untuk custom template

### 13.6 Cannot Edit Group Name

**Problem: Group name field tidak bisa di-edit**

**Cause:** (Fixed in v1.0.1)
- Input field adalah read-only
- Backend didn't process group_name update

**Solusi:** (Already Applied)
- Input field now editable
- Backend update group name in database
- Success message shows updated name

### 13.7 Large CSV Import Slow

**Problem: Import 10,000+ kontak sangat slow**

**Cause:**
- Large data processing takes time
- Browser timeout

**Solusi:**
1. Split CSV into chunks (max 5,000 rows per import)
2. Use staggered imports (multiple sessions)
3. Contact admin untuk batch import via CLI

### 13.8 Format CSV Tidak Dikenali

**Problem: CSV import gagal, error "invalid format"**

**Checklist:**
1. ✅ Delimiter = comma (,) bukan semicolon
2. ✅ No header row (data langsung dari row 1)
3. ✅ Kolom: nama, email, sumber (3 kolom)
4. ✅ Email valid format (name@domain.ext)
5. ✅ File encoding = UTF-8

**Template CSV:**
```csv
PT Asahimas,supplier@asahimas.co.id,Outlook
CV Sejahtera,admin@sejahtera.com,Manual
John Doe,john@example.com,Import
```

---

## 14. QUICK REFERENCE CARD

### Keyboard Shortcuts
| Shortcut | Action |
|----------|--------|
| `Ctrl+K` | Open search |
| `Ctrl+N` | New email |
| `Ctrl+S` | Save (in compose) |
| `Esc` | Close modal/drawer |
| `Alt+→` | Next page |
| `Alt+←` | Previous page |

### Common Workflows

#### Workflow #1: Send to Single Group
```
1. Contacts → Kelola Grup → Buat Grup (if new)
2. Add contacts to group
3. Compose Email → Select group
4. Upload attachment
5. Preview & Cocokkan
6. Send
7. Check Logs
```

**Time Required:** ~10 menit

#### Workflow #2: Send Campaign to Multiple Groups
```
1. Contacts → Kelola Group Order → Buat Group Order
2. Select 2-3 groups (Supplier, Partner, Customer)
3. Compose Email → Select group order
4. Upload lampiran per-group naming
5. Preview & adjust threshold
6. Send
7. Monitor logs
8. Download report
```

**Time Required:** ~15 menit

#### Workflow #3: Bulk Update Recipients
```
1. Contacts → Upload CSV file
2. Verify preview (valid: 490, invalid: 10)
3. Import
4. Organize into groups
5. Ready for campaigns
```

**Time Required:** ~5 menit

---

## 15. SUPPORT & KONTAKNYA

- **Technical Support**: IT@company.com
- **Bug Report**: bugs@company.com
- **Feature Request**: feature@company.com
- **Documentation**: /wiki/email-dispatcher

---

## 16. CHANGELOG

### Version 1.0 - Initial Release (March 5, 2026)
- ✅ User authentication & login
- ✅ Contact management (CRUD)
- ✅ Group management
- ✅ Group Order management
- ✅ Email composition with rich editor
- ✅ Similarity-based attachment matching
- ✅ Outlook integration
- ✅ Email sending via PowerShell/Outlook
- ✅ Logging & history tracking
- ✅ Settings & configuration

### Version 1.0.1 - Bug Fixes
- ✅ Fixed: Group name edit issue
- ✅ Fixed: Bulk send tracking (show all recipients in logs)
- ✅ Fixed: Group Order ID mapping to actual Group IDs
- ✅ Improved: Group send UI/UX

### Upcoming Features (v1.1+)
- 📋 Email template library
- 🔄 Auto-retry failed emails
- 📊 Advanced analytics dashboard
- 🌐 Multi-language support
- 📱 Mobile app
- 🚀 Performance optimization

---

**END OF MANUAL**

*Untuk pertanyaan lebih lanjut, hubungi IT Support atau lihat documentation online.*

*Last Updated: March 5, 2026*
*Version: 1.0*
