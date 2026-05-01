@echo off
echo ========================================
echo   TRONEX TRACKER BOT - Installer
echo ========================================
echo.

:: Check if PHP is installed
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] PHP is not installed or not in PATH!
    echo.
    echo Please install PHP:
    echo   1. Download from https://windows.php.net/download/
    echo   2. Extract to C:\php
    echo   3. Add C:\php to your system PATH
    echo   4. Make sure these extensions are enabled in php.ini:
    echo      - extension=curl
    echo      - extension=gmp  (optional, for large number support)
    echo      - extension=bcmath (optional, for precise decimals)
    echo.
    pause
    exit /b 1
)

echo [OK] PHP found!
php -v
echo.

:: Check required extensions
echo Checking PHP extensions...
php -r "echo extension_loaded('curl') ? '[OK] curl enabled' : '[WARN] curl NOT enabled - REQUIRED!';" 
echo.
php -r "echo extension_loaded('gmp') ? '[OK] gmp enabled' : '[INFO] gmp not enabled - will use fallback';"
echo.
php -r "echo extension_loaded('bcmath') ? '[OK] bcmath enabled' : '[INFO] bcmath not enabled - will use fallback';"
echo.

:: Create data directory
if not exist "data" mkdir data
echo [OK] Data directory ready
echo.

:: Test blockchain connection
echo Testing BSC RPC connection...
php -r "require 'blockchain.php'; $bc = new Blockchain(); $block = $bc->getLatestBlock(); echo $block ? '[OK] Connected! Latest block: '.$block : '[ERROR] Cannot connect to BSC RPC';"
echo.
echo.

echo ========================================
echo   Starting Tronex Tracker Bot...
echo ========================================
echo.
echo Press Ctrl+C to stop the bot.
echo.

php bot.php

pause
