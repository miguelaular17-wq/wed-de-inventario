<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class InventarioPgConfig
{
    public static function load(): array
    {
        $path = config('inventario_pg.config_path');
        if (! $path || ! File::exists($path)) {
            return [];
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? $data : [];
    }

    public static function applyToEnv(): void
    {
        $cfg = self::load();
        if ($cfg === []) {
            return;
        }

        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            return;
        }

        $lines = preg_split('/\r\n|\r|\n/', File::get($envPath));
        $map = [
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) ($cfg['PGHOST'] ?? 'localhost'),
            'DB_PORT' => (string) ($cfg['PGPORT'] ?? 5432),
            'DB_DATABASE' => (string) ($cfg['PGDATABASE'] ?? 'postgres'),
            'DB_USERNAME' => (string) ($cfg['PGUSER'] ?? 'postgres'),
            'DB_PASSWORD' => (string) ($cfg['PGPASSWORD'] ?? ''),
        ];

        $found = array_fill_keys(array_keys($map), false);
        foreach ($lines as $i => $line) {
            foreach ($map as $key => $value) {
                if (str_starts_with($line, $key.'=')) {
                    $lines[$i] = $key.'='.$value;
                    $found[$key] = true;
                }
            }
        }
        foreach ($map as $key => $value) {
            if (! $found[$key]) {
                $lines[] = $key.'='.$value;
            }
        }

        File::put($envPath, implode(PHP_EOL, $lines).PHP_EOL);
    }
}
