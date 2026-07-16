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
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->string('sede')->nullable()->after('tipo_gasto');
            $table->string('placa_vehiculo')->nullable()->after('sede');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->dropColumn(['sede', 'placa_vehiculo']);
        });
    }
};
