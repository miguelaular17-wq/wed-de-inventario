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
        Schema::table('cobranza_resumenes', function (Blueprint $table) {
            $table->dropUnique('cobranza_resumenes_sede_nombre_unique');
        });
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
