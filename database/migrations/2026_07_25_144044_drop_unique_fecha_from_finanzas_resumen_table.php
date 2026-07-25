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
        Schema::table('finanzas_resumen', function (Blueprint $table) {
            $table->dropUnique('finanzas_resumen_fecha_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finanzas_resumen', function (Blueprint $table) {
            $table->unique('fecha');
        });
    }
};
