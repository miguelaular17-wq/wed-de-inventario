<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_comision_diferenciales')) {
            Schema::create('nomina_comision_diferenciales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('periodo_id')->constrained('nomina_periodos')->cascadeOnDelete();
                $table->string('motivo', 255);
                $table->json('config')->comment('Porcentajes usados para recalcular el diferencial');
                $table->date('fecha_pago');
                $table->decimal('tasa_bcv', 12, 4)->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('periodo_id');
            });
        }

        if (! Schema::hasTable('nomina_comision_diferencial_lineas')) {
            Schema::create('nomina_comision_diferencial_lineas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('diferencial_id')->constrained('nomina_comision_diferenciales')->cascadeOnDelete();
                $table->foreignId('empleado_id')->constrained('nomina_empleados')->cascadeOnDelete();
                $table->string('modo', 32);
                $table->boolean('no_pagado')->default(true)->comment('true: no se le pagó la comisión de la quincena');
                $table->decimal('comision_original', 14, 2)->default(0);
                $table->decimal('comision_ajustada', 14, 2)->default(0);
                $table->decimal('abonos', 14, 2)->default(0);
                $table->decimal('retencion_original', 14, 2)->default(0);
                $table->decimal('retencion_ajustada', 14, 2)->default(0);
                $table->decimal('descuentos', 14, 2)->default(0)->comment('Descuentos + préstamos de la quincena');
                $table->decimal('total_original', 14, 2)->default(0);
                $table->decimal('total_ajustado', 14, 2)->default(0);
                $table->decimal('pagado', 14, 2)->default(0);
                $table->decimal('diferencia', 14, 2)->default(0)->comment('USD; positivo se paga en el TXT');
                $table->json('detalle')->nullable();
                $table->timestamps();

                $table->unique(['diferencial_id', 'empleado_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_comision_diferencial_lineas');
        Schema::dropIfExists('nomina_comision_diferenciales');
    }
};
