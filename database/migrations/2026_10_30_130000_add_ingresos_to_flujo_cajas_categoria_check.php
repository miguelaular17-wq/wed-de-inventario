<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Los ingresos de Flujo de Caja se guardan con categoria_egreso = 'ingresos'. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE flujo_cajas DROP CONSTRAINT IF EXISTS flujo_cajas_categoria_egreso_check');
        DB::statement("ALTER TABLE flujo_cajas ADD CONSTRAINT flujo_cajas_categoria_egreso_check CHECK (categoria_egreso::text = ANY (ARRAY['egreso_realizado', 'otros_egresos', 'traslados', 'egreso_divisas', 'ingresos']::text[]))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE flujo_cajas DROP CONSTRAINT IF EXISTS flujo_cajas_categoria_egreso_check');
        DB::statement("ALTER TABLE flujo_cajas ADD CONSTRAINT flujo_cajas_categoria_egreso_check CHECK (categoria_egreso::text = ANY (ARRAY['egreso_realizado', 'otros_egresos', 'traslados', 'egreso_divisas']::text[]))");
    }
};
