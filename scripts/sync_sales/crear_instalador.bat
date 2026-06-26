@echo off
title Generador de Instalador - Sincronizador JRZ-TECH
color 0B

echo.
echo ============================================================
echo   GENERADOR DE INSTALADOR .EXE
echo   Sincronizador de Inventario y Ventas - JRZ-TECH
echo ============================================================
echo.
echo Este script empaqueta el programa en un .exe independiente
echo que NO requiere Python instalado en el equipo destino.
echo.
pause

REM Detectar directorio del script
set "BASE_DIR=%~dp0"
set "DIST_DIR=%BASE_DIR%dist_instalador"
set "EXE_NAME=SincronizadorJRZ"

REM 1. Verificar Python
echo.
echo [1/6] Verificando Python...
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Python no esta instalado. Instala Python 3.9+ primero.
    pause & exit /b 1
)
for /f "tokens=*" %%v in ('python --version 2^>^&1') do echo OK: %%v

REM 2. Instalar dependencias
echo.
echo [2/6] Instalando dependencias necesarias...
python -m pip install psycopg2-binary pyodbc pandas openpyxl pyinstaller --quiet --disable-pip-version-check
if %errorlevel% neq 0 (
    echo [ERROR] No se pudieron instalar las dependencias.
    pause & exit /b 1
)
echo OK: dependencias instaladas.

REM 3. Limpiar builds anteriores
echo.
echo [3/6] Limpiando builds anteriores...
if exist "%BASE_DIR%build" rd /s /q "%BASE_DIR%build"
if exist "%BASE_DIR%dist" rd /s /q "%BASE_DIR%dist"
if exist "%BASE_DIR%%EXE_NAME%.spec" del /q "%BASE_DIR%%EXE_NAME%.spec"
if exist "%DIST_DIR%" rd /s /q "%DIST_DIR%"
echo OK: Directorios limpiados.

REM 4. Compilar con PyInstaller
echo.
echo [4/6] Compilando ejecutable con PyInstaller...
echo (Esto puede tardar 1-3 minutos, por favor espera...)
echo.

REM Moverse al directorio base para evitar problemas de rutas largas
cd /d "%BASE_DIR%"

python -m PyInstaller --onefile --windowed --name "SincronizadorJRZ" --hidden-import psycopg2 --hidden-import pyodbc --hidden-import pandas --hidden-import openpyxl --hidden-import tkinter --hidden-import tkinter.ttk --hidden-import tkinter.scrolledtext --hidden-import tkinter.messagebox "sync_app.py"

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Fallo la compilacion.
    pause & exit /b 1
)
echo.
echo OK: Ejecutable creado.

REM 5. Armar carpeta de distribucion
echo.
echo [5/6] Armando paquete de distribucion...
mkdir "%DIST_DIR%"

copy "%BASE_DIR%dist\%EXE_NAME%.exe" "%DIST_DIR%\" >nul
echo OK: SincronizadorJRZ.exe copiado.

if exist "%BASE_DIR%README.md" (
    copy "%BASE_DIR%README.md" "%DIST_DIR%\" >nul
    echo OK: README.md copiado.
)

echo Creando config.json plantilla...
(
echo {
echo   "sede": "JRZ",
echo   "interval_seconds": 1800,
echo   "billing_db": {
echo     "driver": "{SQL Server}",
echo     "server": "localhost\\SQLEXPRESS",
echo     "database": "suitedb_centro",
echo     "trusted_connection": true,
echo     "query": "SELECT h.fecha_emision, i.articulo, i.cantidad FROM [dbo].[documentos_venta] h WITH (NOLOCK) INNER JOIN [dbo].[documentos_venta_items] i WITH (NOLOCK) ON h.tipo_documento = i.tipo_documento AND h.numero_documento = i.numero_documento WHERE h.tipo_documento = 'FAC' AND h.fecha_emision ^> ? ORDER BY h.fecha_emision ASC"
echo   },
echo   "web_db": {
echo     "host": "aws-1-us-west-2.pooler.supabase.com",
echo     "port": 6543,
echo     "database": "postgres",
echo     "user": "postgres.vsgvjvamjvmtfptnixww",
echo     "password": "W@mqkdhf#snW@68"
echo   }
echo }
) > "%DIST_DIR%\config.json"
echo OK: config.json plantilla creado.

echo Creando instalar.bat...
(
echo @echo off
echo title Instalador - Sincronizador JRZ-TECH
echo color 0A
echo echo ============================================================
echo echo   INSTALADOR - Sincronizador de Inventario JRZ-TECH
echo echo ============================================================
echo echo Creando acceso directo en el Escritorio...
echo set "EXE_PATH=%%~dp0SincronizadorJRZ.exe"
echo powershell -NoProfile -ExecutionPolicy Bypass -Command "$ws = New-Object -ComObject WScript.Shell; $desktop = [Environment]::GetFolderPath('Desktop'); $s = $ws.CreateShortcut($desktop + '\Sincronizador JRZ.lnk'); $s.TargetPath = '%%EXE_PATH%%'; $s.WorkingDirectory = '%%~dp0'; $s.Description = 'Sincronizador JRZ-TECH'; $s.Save()"
echo echo OK: Acceso directo creado en el Escritorio.
echo echo IMPORTANTE: Edita config.json con los datos de conexion de la sede.
echo pause
) > "%DIST_DIR%\instalar.bat"
echo OK: instalar.bat creado.

REM 6. Crear ZIP del paquete
echo.
echo [6/6] Creando archivo ZIP para distribucion...
set "ZIP_PATH=%BASE_DIR%SincronizadorJRZ_Instalador.zip"
if exist "%ZIP_PATH%" del /q "%ZIP_PATH%"

powershell -NoProfile -ExecutionPolicy Bypass -Command "Compress-Archive -Path '%DIST_DIR%\*' -DestinationPath '%ZIP_PATH%' -Force"

if exist "%ZIP_PATH%" (
    echo OK: ZIP creado exitosamente.
) else (
    echo [ADVERTENCIA] No se pudo crear el ZIP automaticamente.
)

REM Limpiar
rd /s /q "%BASE_DIR%build" 2>nul
rd /s /q "%BASE_DIR%dist" 2>nul
del /q "%BASE_DIR%%EXE_NAME%.spec" 2>nul

echo.
echo ============================================================
echo   PAQUETE GENERADO EXITOSAMENTE
echo ============================================================
echo Archivo listo para distribuir:
echo SincronizadorJRZ_Instalador.zip
echo.
explorer "%BASE_DIR%"
pause
