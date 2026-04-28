@echo off
setlocal
set SCRIPT_DIR=%~dp0
set BASE_DIR=%SCRIPT_DIR%\..
set PS_SCRIPT=%BASE_DIR%\ps\send_outlook_emails.ps1
set JOBJSON=%BASE_DIR%\storage\temp\job_test.json
set ACCOUNT=totoh.faturohman@daihatsu.astra.co.id

if not exist "%JOBJSON%" (
  echo {"subject":"Test Subject","body":"<b>Test Body</b>","cc":"","items":[{"id":1,"to":"user1@example.com","attachment":""}]} > "%JOBJSON%"
)

powershell -ExecutionPolicy Bypass -File "%PS_SCRIPT%" -JobJsonPath "%JOBJSON%" -Account "%ACCOUNT%"
