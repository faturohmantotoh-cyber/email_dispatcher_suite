# Gmail SMTP Setup Guide (Gratis)

Panduan lengkap untuk mengkonfigurasi Gmail SMTP sebagai metode pengiriman email gratis.

## 📋 Prasyarat

- Akun Google (Gmail) aktif
- Akses ke akun Gmail untuk generate App Password
- 2-Step Verification diaktifkan di akun Google

## ⚠️ Limitasi Gmail SMTP

- **100 email per hari** untuk akun personal
- **1,500 email per hari** untuk Google Workspace (berbayar)
- Email mungkin masuk folder **Promotions** atau **Spam** untuk recipient baru
- Tidak cocok untuk **marketing/bulk email** (bisa diblokir Google)

## 🚀 Langkah Setup

### Langkah 1: Aktifkan 2-Step Verification

1. Buka https://myaccount.google.com/security
2. Scroll ke bagian **"Signing in to Google"**
3. Klik **2-Step Verification**
4. Ikuti wizard untuk setup 2-Step Verification
5. Pastikan status **"On"**

### Langkah 2: Generate App Password

1. Masih di https://myaccount.google.com/security
2. Scroll ke bagian **"Signing in to Google"**
3. Klik **App passwords**
4. Pilih **Select app** → **Mail**
5. Pilih **Select device** → **Other (Custom name)**
6. Masukkan nama: **Email Dispatcher**
7. Klik **Generate**
8. **Copy App Password yang muncul** (contoh: `xxxx xxxx xxxx xxxx`)

⚠️ **PENTING:** Password ini hanya muncul sekali! Simpan dengan aman.

### Langkah 3: Konfigurasi di Email Dispatcher

1. Login ke aplikasi Email Dispatcher sebagai **admin**
2. Buka **Settings** → **📧 Konfigurasi Email**
3. Pilih **SMTP Direct (Rekomendasi)**
4. Klik **Simpan Konfigurasi**
5. Scroll ke bawah ke **SMTP Configuration Form**
6. Isi form dengan data berikut:

```
SMTP Host: smtp.gmail.com
SMTP Port: 587
SMTP Username: emailanda@gmail.com
SMTP Password: [App Password dari Langkah 2]
Encryption: TLS (Recommended)
From Email: emailanda@gmail.com
From Name: Email Dispatcher
```

7. Klik **Simpan Konfigurasi SMTP**

### Langkah 4: Test Kirim Email

1. Buka menu **Compose**
2. Buat email test dengan subject "Test Gmail SMTP"
3. Kirim ke email Anda sendiri atau email lain
4. Cek email diterima di inbox

## 🔧 Troubleshooting

### Error: "SMTP authentication failed"

**Penyebab:** Password salah atau bukan App Password

**Solusi:**
1. Pastikan pakai **App Password** (bukan password Gmail biasa)
2. Generate App Password baru jika lupa
3. 2-Step Verification harus aktif untuk membuat App Password

### Error: "Failed to connect to SMTP server"

**Penyebab:** Firewall atau network blocking port 587

**Solusi:**
1. Coba ganti port ke **465** dengan encryption **SSL**
2. Check firewall apakah port 587/465 diblokir
3. Coba kirim dari network lain

### Error: "Security: Less secure app access"

**Penyebab:** Google mendeteksi aplikasi sebagai "less secure"

**Solusi:**
1. Pastikan pakai **App Password** (bukan "Less secure app access" yang sudah deprecated)
2. Cek apakah akun Google Anda terkena "suspicious activity"
3. Verifikasi kepemilikan akun di https://myaccount.google.com/security-checkup

### Email masuk ke Spam/Promotions

**Penyebab:** Gmail filtering atau reputation email

**Solusi:**
1. Tambahkan email pengirim ke **Contacts** recipient
2. Minta recipient **mark as not spam**
3. Pastikan **From Name** tidak terlalu generic ("Email Dispatcher" lebih baik dari "noreply")
4. Jangan kirim email yang terlalu mirip spam (all caps, banyak link, dll)

## 📊 Perbandingan dengan Opsi Lain

| Fitur | Gmail SMTP | Outlook COM | Graph API |
|-------|------------|-------------|-----------|
| **Biaya** | Gratis | Gratis | Gratis (butuh Office 365) |
| **Setup** | Mudah | Medium | Sulit (butuh Azure AD) |
| **Limit** | 100/hari | Tidak ada | Sesuai Office 365 quota |
| **Sent Items** | Tidak tersimpan | Di Outlook server | Di mailbox user |
| **Reliability** | Bagus untuk testing | Bagus untuk corporate | Terbaik untuk production |

## 💡 Rekomendasi Penggunaan

### Gunakan Gmail SMTP untuk:
✅ Testing dan development
✅ Small scale operations (< 100 email/hari)
✅ Internal company emails
✅ Proof of concept / demo

### Jangan gunakan Gmail SMTP untuk:
❌ Marketing/bulk email
❌ Email ke banyak recipient berbeda (cold email)
❌ Production dengan volume tinggi
❌ Aplikasi dengan 1000+ user aktif

## 🔄 Upgrade ke SendGrid/Resend (Gratis)

Jika butuh lebih dari 100 email/hari, upgrade ke:

**SendGrid Free (100/hari):**
1. Daftar di https://sendgrid.com/free/
2. Verifikasi sender identity
3. Generate API Key
4. Gunakan sebagai **SMTP** credentials di settings

**Resend Free (100/hari):**
1. Daftar di https://resend.com
2. Verifikasi domain
3. Generate API Key
4. Gunakan sebagai **SMTP** credentials di settings

## 📞 Support

Jika mengalami masalah dengan Gmail SMTP:
1. Check https://support.google.com/mail/answer/7126229
2. Verifikasi App Password masih aktif di https://myaccount.google.com/apppasswords
3. Check apakah akun Google tidak terkena pembatasan

---

**Catatan:** Gmail SMTP adalah opsi gratis yang sempurna untuk testing dan small-scale operations. Untuk production dengan volume tinggi, pertimbangkan untuk upgrade ke SendGrid, Resend, atau Microsoft Graph API.
