# Inventario Android

MVP Android para consultar inventario y gestionar requisiciones entre sedes.

## URL del API

La aplicación usa `https://example.com/` de forma predeterminada. Configure el backend al compilar:

```powershell
.\gradlew.bat assembleDebug -PINVENTARIO_BASE_URL=https://api.midominio.com/
```

La barra final es opcional; Gradle la agrega automáticamente. Los endpoints se consumen bajo `/api/v1`.

Para usar un servidor local desde el emulador, use la dirección del host visible desde Android (normalmente `10.0.2.2`) y configure HTTPS o una política de seguridad de red apropiada para su entorno.

## Preparar Laravel

Antes de instalar el APK, despliegue el backend por HTTPS y ejecute:

```powershell
composer install
php artisan migrate
php artisan optimize:clear
```

La cuenta móvil debe tener el permiso `operacion`. Los roles con sede fija usan su sede asignada; administradores y gerentes pueden seleccionar una sede desde la app.

El APK de depuración queda en `app/build/outputs/apk/debug/app-debug.apk`.
