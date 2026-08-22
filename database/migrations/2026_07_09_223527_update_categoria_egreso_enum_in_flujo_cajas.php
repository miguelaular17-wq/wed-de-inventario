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

        DB::statement("ALTER TABLE flujo_cajas DROP CONSTRAINT IF EXISTS flujo_cajas_categoria_egreso_check");
        DB::statement("ALTER TABLE flujo_cajas ADD CONSTRAINT flujo_cajas_categoria_egreso_check CHECK (categoria_egreso::text = ANY (ARRAY['egreso_realizado'::character varying, 'otros_egresos'::character varying, 'traslados'::character varying]::text[]))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE flujo_cajas DROP CONSTRAINT IF EXISTS flujo_cajas_categoria_egreso_check");
        DB::statement("ALTER TABLE flujo_cajas ADD CONSTRAINT flujo_cajas_categoria_egreso_check CHECK (categoria_egreso::text = ANY (ARRAY['egreso_realizado'::character varying, 'otros_egresos'::character varying]::text[]))");
    }
};
