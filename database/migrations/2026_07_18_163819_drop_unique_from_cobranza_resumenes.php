<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE cobranza_resumenes DROP CONSTRAINT IF EXISTS cobranza_resumenes_sede_nombre_unique');
        } elseif ($driver === 'mysql') {
            $index = collect(DB::select("SHOW INDEX FROM cobranza_resumenes WHERE Key_name = 'cobranza_resumenes_sede_nombre_unique'"));
            if ($index->isNotEmpty()) {
                Schema::table('cobranza_resumenes', function (Blueprint $table) {
                    $table->dropUnique('cobranza_resumenes_sede_nombre_unique');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cobranza_resumenes', function (Blueprint $table) {
            $table->unique('sede_nombre');
        });
    }
};
