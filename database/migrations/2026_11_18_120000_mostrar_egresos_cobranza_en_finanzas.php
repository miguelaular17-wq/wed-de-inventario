<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Egresos de Cobranza con banco real ya tenían espejo en flujo_cajas (origen=cobranza)
 * pero oculto=true. A partir de ahora deben verse en el flujo de Finanzas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flujo_cajas') || ! Schema::hasColumn('flujo_cajas', 'origen')) {
            return;
        }

        DB::table('flujo_cajas')
            ->where('origen', 'cobranza')
            ->where('tipo', 'egreso')
            ->where('oculto', true)
            ->update(['oculto' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('flujo_cajas') || ! Schema::hasColumn('flujo_cajas', 'origen')) {
            return;
        }

        DB::table('flujo_cajas')
            ->where('origen', 'cobranza')
            ->where('tipo', 'egreso')
            ->where('oculto', false)
            ->update(['oculto' => true, 'updated_at' => now()]);
    }
};
