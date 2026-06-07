@echo off
REM CSRF Token Integration Test
REM This script tests the complete CSRF token flow: fetch token, maintain cookies, login

setlocal enabledelayedexpansion

echo.
echo ===============================
echo CSRF Token Integration Test
echo ===============================
echo.

REM Create temporary cookie jar
set "COOKIE_JAR=%TEMP%\csrf_cookies.txt"
del /q "%COOKIE_JAR%" 2>nul

echo Step 1: Fetch CSRF token from server...
echo.
for /f "tokens=*" %%a in ('curl.exe -s -c "%COOKIE_JAR%" http://localhost:8001/get_csrf_token.php') do (
    set "RESPONSE=%%a"
)

REM Extract token using PowerShell
for /f "delims=" %%a in ('powershell -Command "$json = '%RESPONSE%' | ConvertFrom-Json; Write-Host $json.token"') do (
    set "CSRF_TOKEN=%%a"
)

echo Token received: !CSRF_TOKEN:~0,16!...
echo.

REM Show cookie jar
echo Cookies saved:
type "%COOKIE_JAR%"
echo.

echo Step 2: Send login request with CSRF token and cookies...
echo.
for /f "tokens=*" %%a in ('curl.exe -s -b "%COOKIE_JAR%" http://localhost:8001/login.php -d "username=admin123&password=Admin_123&csrf_token=!CSRF_TOKEN!"') do (
    set "LOGIN_RESPONSE=%%a"
)

echo Server response: !LOGIN_RESPONSE!
echo.

REM Check if CSRF validation passed
powershell -Command "
\$response = '!LOGIN_RESPONSE!' | ConvertFrom-Json
if (\$response.success) {
    Write-Host 'Result: SUCCESS - Login succeeded' -ForegroundColor Green
} else {
    \$error = \$response.error
    if (\$error -match 'CSRF|token') {
        Write-Host 'Result: CSRF VALIDATION FAILED' -ForegroundColor Red
        Write-Host \"Error: \$error\" -ForegroundColor Red
    } else {
        Write-Host 'Result: CSRF PASSED, but login failed (expected - no user in DB)' -ForegroundColor Yellow
        Write-Host \"Error: \$error\" -ForegroundColor Yellow
    }
}
"

echo.
echo ===============================

endlocal
