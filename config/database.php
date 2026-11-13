<?php

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'inventario_multisede'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('DB_SCHEMA', 'inventario_v2,public'),
            'sslmode' => 'prefer',
            // El pooler de Supabase en modo transacción (puerto 6543) no conserva sentencias preparadas.
            'options' => extension_loaded('pdo_pgsql') ? [PDO::ATTR_EMULATE_PREPARES => (bool) env('DB_EMULATE_PREPARES', false)] : [],
        ],
        // Base de la web en producción vista desde esta PC (mismas credenciales del sincronizador); para cargas como el archivero.
        'produccion' => [
            'driver' => 'pgsql',
            'host' => env('SYNC_DESKTOP_DB_HOST'),
            'port' => env('SYNC_DESKTOP_DB_PORT', '6543'),
            'database' => env('SYNC_DESKTOP_DB_DATABASE', 'postgres'),
            'username' => env('SYNC_DESKTOP_DB_USER'),
            'password' => env('SYNC_DESKTOP_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'inventario_v2,public',
            'sslmode' => 'require',
            'options' => extension_loaded('pdo_pgsql') ? [PDO::ATTR_EMULATE_PREPARES => true] : [],
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],
];
