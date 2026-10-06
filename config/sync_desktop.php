<?php

return [
    /*
    | Token compartido entre la web y el sincronizador de escritorio.
    | El cliente lo envía en X-Sync-Token o ?token=
    */
    'token' => env('SYNC_DESKTOP_TOKEN', ''),

    /*
    | URL pública base que usan las sedes (sin barra final).
    | Si queda vacío se usa APP_URL.
    */
    'public_base' => rtrim((string) env('SYNC_DESKTOP_PUBLIC_URL', env('APP_URL', '')), '/'),

    /*
    | Credenciales de la base web (Supabase/Postgres) que el escritorio
    | descarga al arrancar. Si un campo está vacío, no se envía.
    */
    // Solo SYNC_DESKTOP_DB_* (no reutilizar DB_* local de desarrollo).
    'web_db' => [
        'host' => env('SYNC_DESKTOP_DB_HOST'),
        'port' => (int) env('SYNC_DESKTOP_DB_PORT', 6543),
        'database' => env('SYNC_DESKTOP_DB_DATABASE', 'postgres'),
        'user' => env('SYNC_DESKTOP_DB_USER'),
        'password' => env('SYNC_DESKTOP_DB_PASSWORD'),
    ],

    'storage_dir' => 'sync-desktop',
    'exe_name' => 'WinSyncService.exe',
    'manifest_name' => 'manifest.json',
];
