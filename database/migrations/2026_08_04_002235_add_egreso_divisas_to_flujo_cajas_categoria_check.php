<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE flujo_cajas DROP CONSTRAINT IF EXISTS flujo_cajas_categoria_egreso_check");
        DB::statement("ALTER TABLE flujo_cajas ADD CONSTRAINT flujo_cajas_categoria_egreso_check CHECK (categoria_egreso::text = ANY (ARRAY['egreso_realizado'::character varying, 'otros_egresos'::character varying, 'traslados'::character varying, 'egreso_divisas'::character varying]::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flujo_cajas_categoria_check', function (Blueprint $table) {
            //
        });
    }
};
