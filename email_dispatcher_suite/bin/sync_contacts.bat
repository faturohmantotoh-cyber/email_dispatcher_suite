@echo off
setlocal
set SCRIPT_DIR=%~dp0
set BASE_DIR=%SCRIPT_DIR%\..
set PS_SCRIPT=%BASE_DIR%\ps\export_outlook_contacts.ps1
set OUTPUT=%BASE_DIR%\storage\contacts_export.csv
set ACCOUNT=totoh.faturohman@daihatsu.astra.co.id

powershell -ExecutionPolicy Bypass -File "%PS_SCRIPT%" -Account "%ACCOUNT%" -OutputCsv "%OUTPUT%"
if %errorlevel% neq 0 (
  echo Failed to export Outlook contacts.
  exit /b 1
)
echo Contacts exported to %OUTPUT%
