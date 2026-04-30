@echo off
REM Build script untuk membuat .exe dari Python Email Sender Engine
REM Menggunakan PyInstaller

title Building Email Sender Engine .exe

echo ==========================================
echo Email Sender Engine - Build to EXE
echo ==========================================
echo.

REM Check Python
python --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Python tidak ditemukan. Mohon install Python terlebih dahulu.
    echo Download dari: https://www.python.org/downloads/
    pause
    exit /b 1
)

REM Check if PyInstaller is installed
echo Checking PyInstaller...
python -c "import PyInstaller" >nul 2>&1
if errorlevel 1 (
    echo [INFO] PyInstaller belum terinstall. Menginstall...
    pip install pyinstaller
    if errorlevel 1 (
        echo [ERROR] Gagal menginstall PyInstaller.
        pause
        exit /b 1
    )
)

REM Check if required packages are installed
echo Checking required packages...
python -c "import requests, win32com.client" >nul 2>&1
if errorlevel 1 (
    echo [INFO] Menginstall dependencies...
    pip install requests pywin32
)

echo.
echo [INFO] Membersihkan build sebelumnya...
if exist "dist" rmdir /s /q "dist"
if exist "build" rmdir /s /q "build"
if exist "*.spec" del /q "*.spec"

echo.
echo [INFO] Building .exe dengan PyInstaller...
echo ==========================================

REM Build dengan PyInstaller
REM --onefile = satu file .exe saja (standalone)
REM --windowed = tanpa console window (untuk production)
REM --icon = custom icon (opsional)
REM --name = nama output file
REM --clean = bersihkan cache sebelum build

pyinstaller --onefile ^
    --name "EmailSenderEngine" ^
    --icon "NONE" ^
    --clean ^
    --noconfirm ^
    --hidden-import=win32com.client ^
    --hidden-import=requests ^
    --hidden-import=json ^
    --hidden-import=time ^
    --hidden-import=uuid ^
    --hidden-import=base64 ^
    --hidden-import=argparse ^
    --hidden-import=logging ^
    --hidden-import=tempfile ^
    --hidden-import=datetime ^
    --hidden-import=pathlib ^
    --hidden-import=os ^
    --hidden-import=sys ^
    email_sender_engine.py

if errorlevel 1 (
    echo.
    echo [ERROR] Build gagal! Periksa error di atas.
    pause
    exit /b 1
)

echo.
echo ==========================================
echo [SUCCESS] Build berhasil!
echo ==========================================
echo.
echo File .exe berada di: dist\EmailSenderEngine.exe
echo.
echo File yang perlu didistribusikan:
echo   - dist\EmailSenderEngine.exe (file utama)
echo   - README.md (dokumentasi)
echo.
echo Copy file-file tersebut ke folder download di web server
echo atau distribusikan langsung ke client.
echo.

REM Create distribution folder
if not exist "dist_package" mkdir "dist_package"
copy "dist\EmailSenderEngine.exe" "dist_package\" >nul
copy "README.md" "dist_package\" >nul

echo [INFO] Package tersedia di folder: dist_package\
echo.

REM Generate .env template file
echo [INFO] Membuat file .env template...
(
echo # Email Sender Engine Configuration
echo # Generated on: %date% %time%
echo.
echo # ============================================
echo # SERVER CONFIGURATION
echo # ============================================
echo # URL server web application Email Dispatcher
echo # Contoh local: http://localhost/email_dispatcher_suite
echo # Contoh production: https://email.company.com
echo # Contoh IP: http://192.168.1.100/email_dispatcher
echo EMAIL_ENGINE_SERVER=http://localhost/email_dispatcher_suite
echo.
echo # ============================================
echo # AUTHENTICATION
echo # ============================================
echo # Token untuk autentikasi ke server
echo # Dapatkan token dari: Web App ^> Settings ^> Client Engine ^> Generate Token
echo EMAIL_ENGINE_TOKEN=your-token-here
echo.
echo # ============================================
echo # OUTLOOK CONFIGURATION ^(Opsional^)
echo # ============================================
echo # Email Outlook yang akan digunakan untuk mengirim
echo # Kosongkan untuk menggunakan default account
echo EMAIL_ENGINE_OUTLOOK_ACCOUNT=
echo.
echo # ============================================
echo # PERFORMANCE SETTINGS
echo # ============================================
echo # Delay antar pengiriman email ^(dalam milidetik^)
echo # 1000 ms = 1 detik
echo EMAIL_ENGINE_DELAY=1000
echo.
echo # Jumlah email maksimum per batch
echo EMAIL_ENGINE_BATCH_SIZE=10
echo.
echo # Interval check antrian dari server ^(dalam detik^)
echo # 60 = check setiap 1 menit
echo EMAIL_ENGINE_INTERVAL=60
echo.
echo # ============================================
echo # LOGGING
echo # ============================================
echo # Level logging: DEBUG, INFO, WARNING, ERROR
echo EMAIL_ENGINE_LOG_LEVEL=INFO
echo.
echo # ============================================
echo # CARA PENGGUNAAN:
echo # ============================================
echo # 1. Isi EMAIL_ENGINE_SERVER dengan URL server Anda
echo # 2. Isi EMAIL_ENGINE_TOKEN dengan token dari web app
echo # 3. Simpan file ini sebagai .env ^(di folder yang sama dengan .exe^)
echo # 4. Jalankan: EmailSenderEngine.exe --config .env --daemon
echo # ============================================
) > "dist_package\.env.template"

echo [SUCCESS] File .env.template dibuat di dist_package\
echo.
echo ============================================
echo INSTRUKSI PENGGUNAAN:
echo ============================================
echo.
echo 1. Copy folder 'dist_package' ke komputer client
echo 2. Rename '.env.template' menjadi '.env'
echo 3. Edit .env dan isi EMAIL_ENGINE_SERVER dan EMAIL_ENGINE_TOKEN
echo 4. Jalankan: EmailSenderEngine.exe --config .env --daemon
echo.
echo ============================================
echo.

pause
