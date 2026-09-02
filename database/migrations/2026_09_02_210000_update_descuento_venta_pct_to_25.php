<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_config')) {
            return;
        }

        $actual = DB::table('nomina_config')->where('clave', 'descuento_venta_pct')->value('valor');
        if ($actual === null || (float) $actual === 20.0) {
            DB::table('nomina_config')->updateOrInsert(
                ['clave' => 'descuento_venta_pct'],
                ['valor' => '25', 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('nomina_config')) {
            return;
        }

        DB::table('nomina_config')->updateOrInsert(
            ['clave' => 'descuento_venta_pct'],
            ['valor' => '20', 'updated_at' => now()]
        );
    }
};
