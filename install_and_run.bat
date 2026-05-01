@echo off
echo ============================================
echo   Tronex Bot - PHP Installer for Windows
echo ============================================
echo.

:: Check if PHP is already installed
where php >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    echo [OK] PHP is already installed!
    php -v
    goto :RUNBOT
)

echo [!] PHP is not installed. Installing PHP 8.3...
echo.

:: Create php directory
if not exist "C:\php" mkdir "C:\php"

:: Download PHP 8.3 (Thread Safe) using PowerShell
echo [*] Downloading PHP 8.3...
powershell -Command "& { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://windows.php.net/downloads/releases/php-8.3.21-Win32-vs16-x64.zip' -OutFile 'C:\php\php.zip' }"

if not exist "C:\php\php.zip" (
    echo [!] Download failed. Please download PHP manually from:
    echo     https://windows.php.net/download/
    echo     Get "VS16 x64 Thread Safe" ZIP
    echo     Extract to C:\php\
    pause
    exit /b 1
)

:: Extract PHP
echo [*] Extracting PHP...
powershell -Command "Expand-Archive -Path 'C:\php\php.zip' -DestinationPath 'C:\php' -Force"
del "C:\php\php.zip"

:: Configure php.ini
echo [*] Configuring PHP...
if exist "C:\php\php.ini-production" (
    copy "C:\php\php.ini-production" "C:\php\php.ini"
)

:: Enable required extensions
powershell -Command "(Get-Content C:\php\php.ini) -replace ';extension=curl', 'extension=curl' -replace ';extension=openssl', 'extension=openssl' -replace ';extension=mbstring', 'extension=mbstring' -replace ';extension=bcmath', 'extension=bcmath' | Set-Content C:\php\php.ini"

:: Add to PATH for current session
set "PATH=C:\php;%PATH%"

:: Add to system PATH permanently
echo [*] Adding PHP to system PATH...
powershell -Command "[Environment]::SetEnvironmentVariable('PATH', 'C:\php;' + [Environment]::GetEnvironmentVariable('PATH', 'User'), 'User')"

echo.
echo [OK] PHP installed successfully!
C:\php\php.exe -v
echo.

:RUNBOT
echo ============================================
echo   Starting Tronex Bot...
echo ============================================
echo.
php "%~dp0bot.php"
pause
