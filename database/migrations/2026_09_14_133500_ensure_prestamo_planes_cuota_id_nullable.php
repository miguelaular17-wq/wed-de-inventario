<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_prestamo_planes')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $nullable = DB::selectOne("
            SELECT is_nullable
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'nomina_prestamo_planes'
              AND column_name = 'cuota_id'
        ");

        if ($nullable && strtoupper((string) $nullable->is_nullable) === 'NO') {
            DB::statement('ALTER TABLE nomina_prestamo_planes ALTER COLUMN cuota_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // No se vuelve a poner NOT NULL: hay planes libres con cuota_id null.
    }
};
