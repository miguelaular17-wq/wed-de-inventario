<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // Para el historial de aumentos de capital
            $table->string('garantia_aumento')->nullable()->after('garantia_documento');
        });

        Schema::table('contrato_cuotas', function (Blueprint $table) {
            // Para marcar cuotas que fueron acumuladas al total en lugar de pagadas
            $table->boolean('acumulada')->default(false)->after('notificaciones_enviadas');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('garantia_aumento');
        });
        Schema::table('contrato_cuotas', function (Blueprint $table) {
            $table->dropColumn('acumulada');
        });
    }
};
