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
        Schema::table('conciliacion_lineas', function (Blueprint $table) {
            $table->unsignedBigInteger('tesoreria_ingreso_id')->nullable()->after('flujo_caja_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conciliacion_lineas', function (Blueprint $table) {
            $table->dropColumn('tesoreria_ingreso_id');
        });
    }
};
