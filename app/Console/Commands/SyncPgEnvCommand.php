<?php

namespace App\Console\Commands;

use App\Support\InventarioPgConfig;
use Illuminate\Console\Command;

class SyncPgEnvCommand extends Command
{
    protected $signature = 'inventario:sync-pg-env';

    protected $description = 'Lee inventario_pg_config copy.json y actualiza .env para PostgreSQL';

    public function handle(): int
    {
        InventarioPgConfig::applyToEnv();
        $this->info('Variables DB_* actualizadas desde '.config('inventario_pg.config_path'));
        $this->comment('Ejecute: php artisan config:clear && php artisan migrate');

        return self::SUCCESS;
    }
}
