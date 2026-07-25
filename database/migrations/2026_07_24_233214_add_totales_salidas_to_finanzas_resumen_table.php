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
            $table->decimal('total_salidas_usd', 20, 2)->default(0)->after('porcentaje_total_diferencial');
            $table->decimal('total_salidas_bs_en_usd', 20, 2)->default(0)->after('total_salidas_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finanzas_resumen', function (Blueprint $table) {
            $table->dropColumn(['total_salidas_usd', 'total_salidas_bs_en_usd']);
        });
    }
};
