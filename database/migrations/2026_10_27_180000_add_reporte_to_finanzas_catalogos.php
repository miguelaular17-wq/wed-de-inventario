<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cuentas de Configuración que también salen en Disponibilidad de Flujo de Caja y en el reporte consolidado. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finanzas_catalogos')) {
            return;
        }
        Schema::table('finanzas_catalogos', function (Blueprint $table) {
            if (! Schema::hasColumn('finanzas_catalogos', 'reporte_categoria')) {
                $table->string('reporte_categoria', 160)->nullable()->comment('Sección de cuentas_bancarias en el reporte; null = no sale');
            }
            if (! Schema::hasColumn('finanzas_catalogos', 'cuenta_bancaria_id')) {
                $table->unsignedBigInteger('cuenta_bancaria_id')->nullable()->comment('Fila de cuentas_bancarias que creó o usa');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('finanzas_catalogos')) {
            return;
        }
        Schema::table('finanzas_catalogos', function (Blueprint $table) {
            foreach (['reporte_categoria', 'cuenta_bancaria_id'] as $col) {
                if (Schema::hasColumn('finanzas_catalogos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
