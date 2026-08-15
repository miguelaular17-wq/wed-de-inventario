<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        // Add oculto column to inventario_v2.productos
        DB::connection('pgsql')->statement(
            'ALTER TABLE inventario_v2.productos ADD COLUMN IF NOT EXISTS oculto BOOLEAN NOT NULL DEFAULT FALSE'
        );
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement(
            'ALTER TABLE inventario_v2.productos DROP COLUMN IF EXISTS oculto'
        );
    }
};
