@echo off
title ERP System - All-in-One Business Suite
color 0A
cls
echo =============================================
echo    ERP System - All-in-One Business Suite
echo    Version 1.0.0
echo =============================================
echo.

:: Check if PHP is available
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP is not installed or not in PATH!
    echo.
    echo To make this portable, download PHP from:
    echo https://windows.php.net/download/
    echo.
    echo Extract php-.zip and add to PATH, or place
    echo php.exe in this same folder.
    echo.
    pause
    exit /b 1
)

:: Check for required SQLite extension
php -m | findstr /i "pdo_sqlite" >nul 2>&1
if %errorlevel% neq 0 (
    echo [WARNING] SQLite extension not enabled!
    echo Attempting to enable pdo_sqlite and sqlite3...
    echo.
    
    :: Find php.ini and enable extensions
    for /f "tokens=*" %%i in ('where php') do set "php_path=%%~dpi"
    if exist "%php_path%php.ini" (
        echo Found php.ini at %php_path%php.ini
        powershell -Command "(Get-Content '%php_path%php.ini') -replace ';extension=pdo_sqlite', 'extension=pdo_sqlite' -replace ';extension=sqlite3', 'extension=sqlite3' | Set-Content '%php_path%php.ini'"
        
        :: Verify again
        php -m | findstr /i "pdo_sqlite" >nul 2>&1
        if %errorlevel% equ 0 (
            echo [OK] SQLite extension enabled successfully!
        ) else (
            echo [ERROR] Could not enable SQLite. Please enable manually:
            echo   Edit %%php_path%%php.ini and uncomment:
            echo     extension=pdo_sqlite
            echo     extension=sqlite3
            pause
            exit /b 1
        )
    ) else (
        echo [ERROR] php.ini not found. Please enable pdo_sqlite extension manually.
        pause
        exit /b 1
    )
)

:: Kill any existing PHP server on port 8080
echo [INFO] Checking for existing servers...
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8080 ^| findstr LISTENING') do (
    taskkill /f /pid %%a >nul 2>&1
)
timeout /t 1 /nobreak >nul

:: Get local IP address
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /i "IPv4" ^| findstr /v "127.0.0.1"') do set "IP=%%a"
set IP=%IP: =%
if "%IP%"=="" set IP=127.0.0.1

echo [INFO] Network IP: %IP%
echo [INFO] Starting PHP server on port 8080...
echo.

start /min "" php -S 0.0.0.0:8080 -t "%~dp0app" "%~dp0app\start.php"

:: Wait for server to start
timeout /t 2 /nobreak >nul

echo =============================================
echo    ERP System is running!
echo =============================================
echo.
echo    Local Access:    http://localhost:8080
echo    Network Access:  http://%IP%:8080
echo.
echo    ===== Default Login Credentials =====
echo        Username: admin
echo        Password: admin@123
echo    ====================================
echo.
echo    Features:
echo    - HR Management        - User Management
echo    - CRM                  - Finance/Accounts
echo    - Ticketing System     - Employee Profiles
echo    - ID Card Generation   - Attendance/Leave
echo    - Activity Tracking    - Backup/Restore
echo    - Reports              - Role-Based Access
echo.
echo    Press any key to open browser...
echo    Close this window to stop the server.
echo =============================================
echo.

start http://localhost:8080
pause >nul

:: Cleanup on close
echo [INFO] Stopping server...
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8080 ^| findstr LISTENING') do (
    taskkill /f /pid %%a >nul 2>&1
)
cls
echo =============================================
echo    ERP System Stopped.
echo    Data is stored in: app\data\
echo    Backups are in:    app\data\backups\
echo =============================================
timeout /t 3 /nobreak >nul
