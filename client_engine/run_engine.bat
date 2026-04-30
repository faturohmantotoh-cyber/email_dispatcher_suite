@echo off
REM Email Sender Engine Launcher for Windows
REM This batch file helps run the Python engine easily

title Email Sender Engine

REM Check if Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Python tidak ditemukan.
    echo.
    echo Mohon install Python terlebih dahulu:
    echo 1. Download dari: https://www.python.org/downloads/
    echo 2. Install dengan centang "Add Python to PATH"
    echo.
    echo Atau gunakan versi .exe (tanpa perlu Python):
    echo Download EmailSenderEngine.exe dari web application
    pause
    exit /b 1
)

REM Check if required packages are installed
echo [INFO] Checking dependencies...
python -c "import requests, win32com.client" >nul 2>&1
if errorlevel 1 (
    echo [INFO] Installing required packages...
    pip install requests pywin32
    if errorlevel 1 (
        echo [ERROR] Gagal menginstall dependencies.
        pause
        exit /b 1
    )
)

REM Check for .env file
if not exist ".env" (
    echo.
    echo ========================================
    echo [WARNING] File .env tidak ditemukan!
    echo ========================================
    echo.
    echo Membuat file .env template...
    echo.
    (
        echo # Email Sender Engine Configuration
        echo.
        echo # URL server web application
        echo # Contoh: http://localhost/email_dispatcher_suite
        echo # Contoh: https://email.company.com
        echo EMAIL_ENGINE_SERVER=http://localhost/email_dispatcher_suite
        echo.
        echo # Token dari web application ^(Settings ^> Client Engine^)
        echo EMAIL_ENGINE_TOKEN=your-token-here
        echo.
        echo # Outlook account ^(opsional, kosongkan untuk default^)
        echo EMAIL_ENGINE_OUTLOOK_ACCOUNT=
        echo.
        echo # Performance settings
        echo EMAIL_ENGINE_DELAY=1000
        echo EMAIL_ENGINE_BATCH_SIZE=10
        echo EMAIL_ENGINE_INTERVAL=60
        echo EMAIL_ENGINE_LOG_LEVEL=INFO
    ) > .env
    
    echo [SUCCESS] File .env.template telah dibuat!
    echo.
    echo ========================================
    echo LANGKAH SELANJUTNYA:
    echo ========================================
    echo 1. Edit file .env di folder ini
    echo 2. Ganti EMAIL_ENGINE_SERVER dengan URL server Anda
    echo 3. Ganti EMAIL_ENGINE_TOKEN dengan token dari web app
    echo 4. Jalankan run_engine.bat lagi
    echo.
    echo ========================================
    echo.
    notepad .env
    pause
    exit /b 0
)

REM Check if token is still default
findstr /C:"EMAIL_ENGINE_TOKEN=your-token-here" .env >nul
if not errorlevel 1 (
    echo.
    echo ========================================
    echo [WARNING] Token belum dikonfigurasi!
    echo ========================================
    echo.
    echo Mohon edit file .env dan isi EMAIL_ENGINE_TOKEN
    echo dengan token yang didapat dari web application:
    echo.
    echo 1. Buka web application
    echo 2. Settings ^> Client Engine
    echo 3. Generate Token
    echo 4. Copy token ke file .env
    echo.
    notepad .env
    pause
    exit /b 0
)

REM Check if server is still default
findstr /C:"EMAIL_ENGINE_SERVER=http://localhost/email_dispatcher_suite" .env >nul
if not errorlevel 1 (
    echo.
    echo [INFO] Server URL masih menggunakan default ^(localhost^)
    echo Pastikan URL sudah benar atau biarkan jika server di localhost.
    echo.
)

REM Run the engine
echo.
echo ========================================
echo Starting Email Sender Engine...
echo ========================================
echo Press Ctrl+C to stop
echo.

python email_sender_engine.py --config .env --daemon

if errorlevel 1 (
    echo.
    echo ========================================
    echo [ERROR] Engine stopped with error.
    echo ========================================
    echo Check logs in: %USERPROFILE%\.email_engine\logs\
    echo.
    pause
)
