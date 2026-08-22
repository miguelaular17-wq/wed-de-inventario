<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE inventario_v2.productos ADD COLUMN IF NOT EXISTS oculto BOOLEAN NOT NULL DEFAULT FALSE'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE inventario_v2.productos DROP COLUMN IF EXISTS oculto'
        );
    }
};
