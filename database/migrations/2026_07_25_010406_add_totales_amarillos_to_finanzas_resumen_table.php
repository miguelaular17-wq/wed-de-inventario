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
            $table->decimal('total_egresos_usd', 15, 2)->default(0)->after('porcentaje_total_diferencial');
            $table->decimal('total_egresos_bs_usd', 15, 2)->default(0)->after('total_egresos_usd');
            $table->decimal('total_otros_usd', 15, 2)->default(0)->after('total_egresos_bs_usd');
            $table->decimal('total_otros_bs_usd', 15, 2)->default(0)->after('total_otros_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finanzas_resumen', function (Blueprint $table) {
            $table->dropColumn([
                'total_egresos_usd',
                'total_egresos_bs_usd',
                'total_otros_usd',
                'total_otros_bs_usd'
            ]);
        });
    }
};
