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

        DB::table('nomina_config')->updateOrInsert(
            ['clave' => 'comision_nunes_pct'],
            ['valor' => '0.60', 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('nomina_config')) {
            return;
        }

        DB::table('nomina_config')->where('clave', 'comision_nunes_pct')->delete();
    }
};
