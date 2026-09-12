@echo off
setlocal EnableExtensions
cd /d "%~dp0"

set "NOPAUSE=0"
if /I "%~1"=="/nopause" set "NOPAUSE=1"

set "TOOLS=%~dp0tools"
set "PHP_DIR=%TOOLS%\php"
set "ZIP=%TOOLS%\php-portable.zip"
set "URL=https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip"

if exist "%PHP_DIR%\php.exe" (
  echo PHP portable ya esta en:
  echo   %PHP_DIR%\php.exe
  echo.
  if "%NOPAUSE%"=="0" pause
  exit /b 0
)

where tar >nul 2>nul
if errorlevel 1 (
  echo Este Windows necesita "tar" para descomprimir, o descomprime manualmente el zip.
)

where curl >nul 2>nul
if errorlevel 1 (
  echo No se encontro curl. Descarga PHP manualmente:
  echo   %URL%
  echo y descomprimelo en:
  echo   %PHP_DIR%
  echo.
  if "%NOPAUSE%"=="0" pause
  exit /b 1
)

if not exist "%TOOLS%" mkdir "%TOOLS%"
if not exist "%PHP_DIR%" mkdir "%PHP_DIR%"

echo Descargando PHP portable...
curl -L --fail --retry 3 -o "%ZIP%" "%URL%"
if errorlevel 1 (
  echo No se pudo descargar PHP portable.
  echo Prueba otra URL en https://windows.php.net/download/ o descomprime a mano en:
  echo   %PHP_DIR%
  echo.
  if "%NOPAUSE%"=="0" pause
  exit /b 1
)

echo Descomprimiendo...
tar -xf "%ZIP%" -C "%PHP_DIR%"
if errorlevel 1 (
  echo No se pudo descomprimir. Abre el zip y extrae todo en:
  echo   %PHP_DIR%
  echo.
  if "%NOPAUSE%"=="0" pause
  exit /b 1
)

if not exist "%PHP_DIR%\php.exe" (
  for /d %%D in ("%PHP_DIR%\php-*") do (
    if exist "%%D\php.exe" (
      xcopy "%%D\*" "%PHP_DIR%\" /E /I /Y >nul
    )
  )
)

if not exist "%PHP_DIR%\php.exe" (
  echo No aparecio php.exe dentro del zip.
  echo Extrae manualmente el contenido en:
  echo   %PHP_DIR%
  echo.
  if "%NOPAUSE%"=="0" pause
  exit /b 1
)

if not exist "%PHP_DIR%\php.ini" if exist "%PHP_DIR%\php.ini-production" (
  copy /Y "%PHP_DIR%\php.ini-production" "%PHP_DIR%\php.ini" >nul
)

powershell -NoProfile -Command ^
  "$ini=Join-Path '%PHP_DIR%' 'php.ini';" ^
  "if(Test-Path $ini){" ^
  "  $c=Get-Content $ini -Raw;" ^
  "  $c=$c -replace ';extension=pdo_pgsql','extension=pdo_pgsql';" ^
  "  $c=$c -replace ';extension=pgsql','extension=pgsql';" ^
  "  $c=$c -replace ';extension=zip','extension=zip';" ^
  "  $c=$c -replace ';extension=openssl','extension=openssl';" ^
  "  $c=$c -replace ';extension_dir = \"ext\"','extension_dir = \"ext\"';" ^
  "  Set-Content -Path $ini -Value $c -Encoding ASCII;" ^
  "}"

del /f /q "%ZIP%" >nul 2>nul

echo.
echo PHP portable listo:
echo   %PHP_DIR%\php.exe
echo.
if "%NOPAUSE%"=="0" (
  echo Ahora ejecuta: exportar_precios.bat
  echo.
  pause
)
exit /b 0
