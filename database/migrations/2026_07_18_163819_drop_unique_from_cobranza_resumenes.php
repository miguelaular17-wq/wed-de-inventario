<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE cobranza_resumenes DROP CONSTRAINT IF EXISTS cobranza_resumenes_sede_nombre_unique');
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
