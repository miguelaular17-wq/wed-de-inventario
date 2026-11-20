<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nomina_pagos_manuales')) {
            return;
        }

        Schema::create('nomina_pagos_manuales', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 16); // NOMINA | COMISION
            $table->unsignedBigInteger('periodo_id');
            $table->unsignedBigInteger('empleado_id');
            $table->decimal('monto_usd', 12, 2);
            $table->decimal('tasa_bcv', 12, 4)->nullable();
            $table->string('estado', 16)->default('PENDIENTE');
            $table->unsignedBigInteger('enviado_by')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->unsignedBigInteger('pagado_by')->nullable();
            $table->timestamp('pagado_at')->nullable();
            $table->unsignedBigInteger('flujo_caja_id')->nullable();
            $table->timestamps();

            $table->index(['estado', 'tipo']);
            $table->index(['periodo_id', 'tipo']);
            $table->unique(['tipo', 'periodo_id', 'empleado_id'], 'nomina_pagos_manuales_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_pagos_manuales');
    }
};
