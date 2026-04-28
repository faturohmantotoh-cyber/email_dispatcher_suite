# Email Dispatcher Suite (PHP + Outlook + Laragon)

Paket web PHP + database untuk mengirim email via **Outlook Desktop** berbasis **kemiripan nama lampiran ⇄ penerima**, lengkap dengan:

1. **Auto sync kontak** dari Outlook → CSV → Database
2. **Upload/scan** file lampiran
3. **Form pengisian** (pilih kontak satu-satu atau *select all*, subject, body, CC)
4. **Tombol Kirim** yang memasangkan lampiran ke penerima berdasarkan **similarity** (nama file vs nama/email)
5. **Log & rekap** sukses/gagal per email

> Disiapkan agar berjalan di **Laragon (Windows)** tanpa dependency tambahan (tanpa Composer). Komunikasi ke Outlook menggunakan **PowerShell + COM**.

---

## 1) Instalasi Cepat

1. Ekstrak folder ini ke: `C:\laragon\www\email_dispatcher_suite`
2. Buat database MySQL:
   - Buka **HeidiSQL**/phpMyAdmin lalu jalankan `db/install.sql`
3. Konfigurasi `config.php` bila perlu (DB/password & akun Outlook):
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'email_dispatcher');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('OUTLOOK_ACCOUNT', 'totoh.faturohman@daihatsu.astra.co.id');
   ```
4. Akses di browser: `http://localhost/email_dispatcher_suite/public/`

> Pastikan **Outlook Desktop** telah login pada akun yang sama dengan `OUTLOOK_ACCOUNT`.

---

## 2) Alur Operasi Harian

**A. Sinkron Kontak dari Outlook**  
Menu: **Kontak** → klik `Export dari Outlook → CSV` → lalu `Import CSV → Database`.

- Script PowerShell: `ps/export_outlook_contacts.ps1`  
  Mengekspor **Nama** & **Email** dari folder **Contacts** akun Outlook ke `storage/contacts_export.csv`, kemudian di-*import* ke tabel `contacts`.

**B. Upload Lampiran**  
Menu: **Upload Lampiran** → unggah semua file yang akan dikirim.  
Sistem mencatat ke tabel `attachments` (nama, path, ukuran, hash).

**C. Compose & Kirim (Similarity)**  
Menu: **Kirim Email (Similarity)** → isi **Subject/Body/CC** → pilih penerima (*select all* bisa) → **Preview & Cocokkan**.  
Sistem akan menghitung **skor kemiripan 0–100** antara nama file vs **nama**/**email** penerima dan memilih **skor terbaik** per penerima.  
Jika skor ≥ threshold (default 60), lampiran akan dipasangkan. Lanjut **Kirim**.

**D. Log & Rekap**  
Menu: **Log & Rekap** → lihat status per *job* dan per *item*.  
Sukses/gagal & pesan error dari Outlook akan tercatat.

---

## 3) Komponen Teknis

- **PHP (tanpa framework)**
  - `public/` : halaman UI dan endpoint
  - `lib/` : koneksi DB (PDO), utilitas, **similarity** berbasis `similar_text`
  - `storage/` : file lampiran, CSV kontak, hasil sementara JSON
- **MySQL**
  - Tabel: `contacts`, `attachments`, `mail_jobs`, `mail_job_items`
- **PowerShell + Outlook COM**
  - `ps/export_outlook_contacts.ps1` : ekspor kontak → CSV
  - `ps/send_outlook_emails.ps1` : kirim email massal berdasarkan *job JSON*

> Pengiriman email dilakukan oleh **Outlook Desktop** (bukan SMTP). Ini memanfaatkan kredensial & profil Outlook yang sudah login.

---

## 4) Otomasi (Optional)

### Task Scheduler: Auto Sync Kontak Harian
1. Buka **Task Scheduler** → *Create Task*
2. **Triggers**: Daily, mis. 08:00
3. **Actions**:
   - *Start a Program* → `C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe`
   - *Arguments*: `-ExecutionPolicy Bypass -File "C:\laragon\www\email_dispatcher_suite\ps\export_outlook_contacts.ps1" -Account "totoh.faturohman@daihatsu.astra.co.id" -OutputCsv "C:\laragon\www\email_dispatcher_suite\storage\contacts_export.csv"`

Setelah export, masuk ke menu **Kontak** dan klik `Import CSV → Database` (atau buat *scheduled task* kedua ke endpoint import memakai `curl`).

### Uji Kirim Satu Email
Jalankan `bin/send_test_mail.bat` (mengirim ke `user1@example.com`).

---

## 5) Keamanan & Catatan

- **Upload** dibatasi ekstensi umum; tambahkan sesuai kebutuhan di `public/upload.php`.
- **Outlook Security Prompt**: Beberapa instalasi Outlook menampilkan peringatan saat *automation*. Pastikan Outlook terbuka & *programmatic access* diizinkan (Trust Center) atau jalankan Outlook dengan profil terpercaya.
- **Body HTML**: `send_outlook_emails.ps1` mengirim **HTML**; pastikan konten *safe*.
- **Similarity Threshold**: Default 60; sesuaikan di halaman compose.
- **Logging**: Detail item tersimpan di `mail_job_items`. Hasil mentah PowerShell juga disimpan sebagai JSON di `storage/temp/`.

---

## 6) Troubleshooting

- **Account tidak ditemukan**: Script akan pakai akun default Outlook. Cek `OUTLOOK_ACCOUNT` dan profil Outlook.
- **Attachment path**: Pastikan path lengkap Windows (skrip memastikan path absolut saat menyimpan).
- **PHP tidak bisa panggil PowerShell**: Pastikan `powershell.exe` ada di PATH dan hak eksekusi *Bypass* tidak diblokir oleh kebijakan lokal.

---

## 7) Roadmap (Opsional)
- Mode **multi-attachment per penerima**
- Template **body email** & variabel (mis. `{{name}}`)
- Integrasi **Graph API** untuk kirim tanpa Outlook (memerlukan Azure app)
- UI DataTables + pencarian cepat

---

**Dibuat untuk: Totoh Fathurohman** — siap jalan di **Laragon**.
