# Build Instructions - Email Sender Engine .exe

Panduan untuk membuat file .exe dari Python script agar client tidak perlu install Python.

## Prerequisites

1. **Python 3.7+** terinstall di komputer build
2. **Windows** (karena target adalah Windows executable)
3. **Microsoft Outlook** (untuk testing, optional)

## Install Build Dependencies

```bash
pip install pyinstaller requests pywin32
```

## Build .exe (3 Cara)

### Cara 1: Batch File (Paling Mudah)

```cmd
cd client_engine
build_exe.bat
```

Batch file akan:
- Check prerequisites
- Install PyInstaller jika belum ada
- Build .exe dengan PyInstaller
- Copy hasil ke folder `dist_package`

### Cara 2: Manual PyInstaller

```bash
cd client_engine
pyinstaller --onefile --name "EmailSenderEngine" email_sender_engine.py
```

Hasil ada di `dist/EmailSenderEngine.exe`

### Cara 3: Spec File (Advanced)

```bash
cd client_engine
pyinstaller EmailSenderEngine.spec
```

Spec file sudah dikonfigurasi dengan:
- Hidden imports untuk win32com dan requests
- Excludes untuk library yang tidak perlu (numpy, matplotlib, dll)
- UPX compression untuk ukuran lebih kecil

## Setelah Build

1. **Copy .exe ke web server:**
   ```
   client_engine/dist/EmailSenderEngine.exe → client_engine/dist/EmailSenderEngine.exe
   ```

2. **File yang didistribusikan ke client:**
   - `EmailSenderEngine.exe` (standalone)
   - `README.md` (dokumentasi)

3. **Test download dari web:**
   - Buka Settings → Client Engine
   - Cek apakah tombol download .exe muncul
   - Download dan test jalankan di komputer client

## Ukuran File

- **Hasil .exe**: ~10-15 MB
- **Termasuk**: Python runtime, requests, pywin32, semua dependencies
- **Client perlu**: Windows 10/11, Microsoft Outlook terinstall

## Troubleshooting Build

### Error: "pyinstaller not found"
```bash
pip install pyinstaller
```

### Error: "win32com not found"
```bash
pip install pywin32
```

### Error: "Access denied"
- Jalankan Command Prompt sebagai Administrator
- Atau gunakan PowerShell dengan elevated privileges

### Ukuran file terlalu besar
- Sudah dioptimasi dengan excludes di spec file
- Library yang diexclude: numpy, pandas, matplotlib, scipy, tkinter, PyQt, wx
- Jika masih besar, bisa gunakan UPX compression

## Automatisasi Build

Untuk CI/CD atau automated build, gunakan command:

```bash
pyinstaller --onefile --name "EmailSenderEngine" --clean --noconfirm email_sender_engine.py
```

## Keamanan

- .exe yang di-build adalah standalone dan tidak perlu dependency eksternal
- Token dan credentials disimpan di file .env terpisah (jangan hardcode!)
- Log disimpan di `%USERPROFILE%\.email_engine\logs\`

## Distribusi

Setelah build, ada beberapa cara distribusi:

1. **Web Download**: Upload `EmailSenderEngine.exe` ke `client_engine/dist/` di web server
2. **File Share**: Copy ke network share atau USB drive
3. **Email**: Kirim sebagai attachment (mungkin diblokir antivirus, gunakan zip dengan password)
4. **Self-hosted**: Sediakan link download dari server internal

## Update Version

Untuk update ke versi baru:

1. Increment `VERSION` di `email_sender_engine.py`
2. Rebuild dengan `build_exe.bat`
3. Replace file .exe di web server
4. Inform user untuk download versi baru

## Catatan Penting

- .exe yang di-build hanya untuk **Windows 64-bit**
- Tidak compatible dengan 32-bit Windows
- Tidak compatible dengan Linux/Mac
- Outlook harus terinstall di komputer client
- Antivirus mungkin flag sebagai suspicious karena pyinstaller, whitelist jika perlu

---

**Build Date**: Generated automatically
**Version**: 1.0.0
