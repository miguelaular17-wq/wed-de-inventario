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
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->string('categoria_reporte')->nullable();
            $table->boolean('mostrar_en_principal')->default(true);
        });

        Schema::table('finanzas_resumen', function (Blueprint $table) {
            $table->decimal('tasa_paralelo', 15, 2)->default(0);
            $table->decimal('bloqueado_compra_divisas', 15, 2)->default(0);
            $table->decimal('fondos_no_disponibles', 15, 2)->default(0);
            $table->decimal('titulos_cobertura_espera', 15, 2)->default(0);
            $table->decimal('titulos_cobertura_aprobados', 15, 2)->default(0);
            $table->decimal('retenido_pagos_planificados', 15, 2)->default(0);
            $table->decimal('compromisos_pago_bs', 15, 2)->default(0);
            $table->decimal('compromisos_pago_usd', 15, 2)->default(0);
        });

        Schema::create('planificacion_pagos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->nullable();
            $table->string('razon_social')->nullable();
            $table->decimal('total_bs', 15, 2)->default(0);
            $table->decimal('tasa', 15, 2)->default(0);
            $table->decimal('total_usd', 15, 2)->default(0);
            $table->string('factura')->nullable();
            $table->string('concepto')->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planificacion_pagos');
        Schema::table('finanzas_resumen', function (Blueprint $table) {
            $table->dropColumn([
                'tasa_paralelo', 'bloqueado_compra_divisas', 'fondos_no_disponibles',
                'titulos_cobertura_espera', 'titulos_cobertura_aprobados',
                'retenido_pagos_planificados', 'compromisos_pago_bs', 'compromisos_pago_usd'
            ]);
        });
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->dropColumn(['categoria_reporte', 'mostrar_en_principal']);
        });
    }
};
