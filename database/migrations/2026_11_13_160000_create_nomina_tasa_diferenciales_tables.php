<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_tasa_diferenciales')) {
            Schema::create('nomina_tasa_diferenciales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('periodo_id')->constrained('nomina_periodos')->cascadeOnDelete();
                $table->string('motivo', 255);
                $table->date('fecha_pago');
                $table->decimal('tasa_pagada', 12, 4)->comment('Tasa con la que se pagó la nómina de la quincena');
                $table->decimal('tasa_nueva', 12, 4)->comment('Tasa con la que se debió pagar');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('periodo_id');
            });
        }

        if (! Schema::hasTable('nomina_tasa_diferencial_lineas')) {
            Schema::create('nomina_tasa_diferencial_lineas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('diferencial_id')->constrained('nomina_tasa_diferenciales')->cascadeOnDelete();
                $table->foreignId('empleado_id')->constrained('nomina_empleados')->cascadeOnDelete();
                $table->decimal('total_usd', 14, 2)->default(0)->comment('Neto a pagar de la quincena en USD');
                $table->decimal('bs_pagado', 14, 2)->default(0);
                $table->decimal('bs_nuevo', 14, 2)->default(0);
                $table->decimal('diferencia_bs', 14, 2)->default(0)->comment('Se paga en el TXT');
                $table->timestamps();

                $table->unique(['diferencial_id', 'empleado_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_tasa_diferencial_lineas');
        Schema::dropIfExists('nomina_tasa_diferenciales');
    }
};
