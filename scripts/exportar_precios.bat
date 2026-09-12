@echo off
setlocal EnableExtensions
cd /d "%~dp0"

set "PHP_EXE="
where php >nul 2>nul && for /f "delims=" %%I in ('where php') do (
  if not defined PHP_EXE set "PHP_EXE=%%I"
)

if not defined PHP_EXE if exist "%~dp0tools\php\php.exe" set "PHP_EXE=%~dp0tools\php\php.exe"

if not defined PHP_EXE (
  echo No se encontro PHP. Intentando descargar PHP portable...
  echo.
  call "%~dp0descargar_php_portatil.bat" /nopause
  if exist "%~dp0tools\php\php.exe" set "PHP_EXE=%~dp0tools\php\php.exe"
)

if not defined PHP_EXE (
  echo No se encontro PHP.
  echo Ejecuta primero: descargar_php_portatil.bat
  echo.
  pause
  exit /b 1
)

echo Usando: %PHP_EXE%
echo Generando Excel desde produccion ^(solo lectura^)...
echo.

"%PHP_EXE%" "%~dp0exportar_precios_produccion.php" %*
set "ERR=%ERRORLEVEL%"

echo.
if not "%ERR%"=="0" (
  echo Fallo con codigo %ERR%.
  pause
  exit /b %ERR%
)

echo Listo.
pause
exit /b 0
