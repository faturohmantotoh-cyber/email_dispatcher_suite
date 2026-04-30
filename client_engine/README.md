# Email Sender Engine - Client Application

Aplikasi Python untuk mengirim email dari Outlook lokal dengan mengambil antrian dari server.

## Fitur

- ✅ Menggunakan Outlook COM di komputer client (tidak perlu Outlook di server)
- ✅ Autentikasi dengan token unik per user
- ✅ Mengambil antrian email dari server database
- ✅ Support attachments
- ✅ Logging lengkap
- ✅ Bisa jalan sebagai daemon (continuous) atau single execution
- ✅ Delay antar pengiriman untuk menghindari rate limiting

## Requirements

- Windows dengan Microsoft Outlook terinstall
- Python 3.7 atau lebih tinggi
- pip (Python package manager)

## Installation

Ada 2 cara menggunakan Email Sender Engine:

### **Opsi 1: .exe File (Rekomendasi - Tanpa Python)**

Gunakan ini jika client TIDAK punya Python terinstall.

#### 1. Download .exe File

Download file `EmailSenderEngine.exe` dari web application:
1. Login ke Email Dispatcher web application
2. Buka **Settings** → **Client Engine**
3. Download **EmailSenderEngine.exe**

#### 2. Buat Config File

Buat file `.env` di folder yang sama dengan .exe:

```env
EMAIL_ENGINE_SERVER=http://your-server.com
EMAIL_ENGINE_TOKEN=your-token-here
EMAIL_ENGINE_OUTLOOK_ACCOUNT=your-email@company.com
EMAIL_ENGINE_DELAY=1000
EMAIL_ENGINE_BATCH_SIZE=10
EMAIL_ENGINE_INTERVAL=60
```

#### 3. Jalankan

Double-click `EmailSenderEngine.exe` atau jalankan dari command prompt:

```cmd
EmailSenderEngine.exe --config .env --daemon
```

---

### **Opsi 2: Python Script (Untuk Development)**

Gunakan ini jika client sudah punya Python terinstall.

#### 1. Install Python Dependencies

```bash
pip install requests pywin32
```

#### 2. Dapatkan Token dari Web Application

1. Login ke Email Dispatcher web application
2. Buka **Settings** → **Client Engine**
3. Generate token baru
4. Copy token untuk digunakan di client

#### 3. Konfigurasi Engine

**Command Line:**

```bash
python email_sender_engine.py --server http://your-server.com --token YOUR_TOKEN
```

**Environment File:**

Buat file `.env` di folder yang sama:

```env
EMAIL_ENGINE_SERVER=http://your-server.com
EMAIL_ENGINE_TOKEN=your-token-here
EMAIL_ENGINE_OUTLOOK_ACCOUNT=your-email@company.com
EMAIL_ENGINE_DELAY=1000
EMAIL_ENGINE_BATCH_SIZE=10
EMAIL_ENGINE_INTERVAL=60
```

Kemudian jalankan:

```bash
python email_sender_engine.py --config .env --daemon
```

## Usage

### Single Execution (Sekali jalan)

```bash
python email_sender_engine.py --server http://server.com --token ABC123
```

### Daemon Mode (Continuous)

```bash
python email_sender_engine.py --server http://server.com --token ABC123 --daemon
```

Engine akan terus berjalan dan check antrian setiap 60 detik (default).

### dengan Outlook Account Tertentu

```bash
python email_sender_engine.py --server http://server.com --token ABC123 --outlook-account user@company.com
```

## Configuration Options

| Environment Variable | Default | Description |
|---------------------|---------|-------------|
| `EMAIL_ENGINE_SERVER` | - | URL server (wajib) |
| `EMAIL_ENGINE_TOKEN` | - | API token (wajib) |
| `EMAIL_ENGINE_OUTLOOK_ACCOUNT` | - | Account Outlook spesifik (optional) |
| `EMAIL_ENGINE_DELAY` | 1000 | Delay antar email (ms) |
| `EMAIL_ENGINE_BATCH_SIZE` | 10 | Max email per batch |
| `EMAIL_ENGINE_INTERVAL` | 60 | Interval check antrian (detik) |
| `EMAIL_ENGINE_LOG_LEVEL` | INFO | Level logging (DEBUG/INFO/WARNING/ERROR) |

## Log Files

Log disimpan di:
```
C:\Users\[Username]\.email_engine\logs\
```

Format: `engine_[ENGINE_ID]_[YYYYMMDD].log`

## Troubleshooting

### Error: "Failed to initialize Outlook"

**Penyebab**: Outlook tidak terinstall atau COM tidak tersedia

**Solusi**:
1. Pastikan Microsoft Outlook terinstall
2. Jalankan Outlook sekali untuk setup profile
3. Coba run sebagai Administrator

### Error: "Failed to fetch queue"

**Penyebab**: Token salah atau network issue

**Solusi**:
1. Verifikasi token di web application (Settings → Client Engine)
2. Check koneksi ke server
3. Pastikan firewall tidak memblokir

### Error: "No module named 'win32com'"

**Solusi**:
```bash
pip install pywin32
```

### Email tidak terkirim tapi tidak ada error

**Penyebab**: Outlook security prompt atau macro settings

**Solusi**:
1. Buka Outlook
2. Check "Programmatic Access" settings di Trust Center
3. Set ke "Never warn me about suspicious activity" (jika di trusted environment)

## Security

- Token harus dijaga kerahasiaannya (seperti password)
- Jangan commit token ke version control
- Gunakan HTTPS untuk production
- Rotate token secara berkala di web application

## Windows Task Scheduler (Auto Start)

Untuk auto-start saat login:

1. Buka Task Scheduler
2. Create Basic Task
3. Trigger: When I log on
4. Action: Start a program
5. Program: `pythonw.exe` (gunakan pythonw untuk no console window)
6. Arguments: `C:\path\to\email_sender_engine.py --config C:\path\to\.env --daemon`

## Support

Jika ada masalah:
1. Check log files di `.email_engine\logs\`
2. Verifikasi token masih aktif di web application
3. Pastikan Outlook berjalan normal
4. Contact administrator

## Build .exe dari Source (Untuk Admin/Developer)

Jika Anda perlu membuat .exe sendiri dari source code Python:

### Prerequisites

1. Python 3.7+ terinstall
2. Install PyInstaller:
   ```bash
   pip install pyinstaller
   ```

### Build dengan Batch File (Mudah)

1. Jalankan `build_exe.bat`:
   ```cmd
   build_exe.bat
   ```

2. Hasil build ada di folder `dist\EmailSenderEngine.exe`

3. Copy file ke web server untuk didistribusikan

### Build Manual dengan PyInstaller

```bash
pyinstaller --onefile --name "EmailSenderEngine" email_sender_engine.py
```

### Build dengan Spec File (Advanced)

```bash
pyinstaller EmailSenderEngine.spec
```

### Catatan Build

- Hasil .exe standalone (tidak perlu Python di client)
- Semua dependencies (requests, pywin32) sudah included
- File size sekitar 10-15 MB
- Compatible dengan Windows 10/11 64-bit

---

## Windows Task Scheduler (Auto Start)

Untuk auto-start saat login:

### Untuk .exe Version:

1. Buka Task Scheduler
2. Create Basic Task
3. Trigger: **When I log on**
4. Action: **Start a program**
5. Program: `C:\path\to\EmailSenderEngine.exe`
6. Arguments: `--config C:\path\to\.env --daemon`
7. Centang **Run whether user is logged on or not** (opsional)

### Untuk Python Version:

1. Buka Task Scheduler
2. Create Basic Task
3. Trigger: **When I log on**
4. Action: **Start a program**
5. Program: `pythonw.exe` (gunakan pythonw untuk no console window)
6. Arguments: `C:\path\to\email_sender_engine.py --config C:\path\to\.env --daemon`

---

## License

Internal Use Only - Email Dispatcher Suite
