<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_contrato')->unique();
            $table->string('cliente');
            $table->string('garantia')->nullable();
            $table->string('contacto')->nullable(); // Quien hace los pagos / responsable
            $table->string('telefono')->nullable();
            $table->string('sede')->nullable();
            $table->decimal('capital', 14, 2)->default(0);
            $table->decimal('interes_porcentaje', 8, 4)->default(0);
            $table->decimal('cuota_fija', 14, 2)->default(0);
            $table->decimal('total_a_pagar', 14, 2)->default(0);
            $table->date('fecha_inicio')->nullable();
            $table->string('frecuencia')->default('MENSUAL'); // MENSUAL / QUINCENAL
            $table->unsignedBigInteger('responsable_id')->nullable(); // asesor de cobranza
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('responsable_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('contrato_cuotas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contrato_id');
            $table->integer('numero_cuota')->default(0);
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('monto', 14, 2)->default(0);
            $table->string('estatus')->default('pendiente'); // pendiente / pagado / vencido / parcial
            $table->date('fecha_pago')->nullable();
            $table->string('forma_pago')->nullable(); // ZELLE / EFECTIVO / TRANSFERENCIA
            $table->decimal('monto_pagado', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2)->default(0);
            $table->json('notificaciones_enviadas')->nullable(); // ['7_antes', '3_antes', 'dia', etc.]
            $table->timestamps();

            $table->foreign('contrato_id')->references('id')->on('contratos')->cascadeOnDelete();
            $table->index(['contrato_id', 'fecha_vencimiento']);
            $table->index(['estatus', 'fecha_vencimiento']);
        });

        Schema::create('contrato_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contrato_id');
            $table->unsignedBigInteger('cuota_id')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->dateTime('fecha_hora');
            $table->string('resultado')->default('CONTACTADO');
            // CONTACTADO / NO_CONTESTA / PROMESA_PAGO / BUZON_MENSAJES / SIN_SEÑAL / PAGO_PARCIAL
            $table->date('fecha_prometida_pago')->nullable();
            $table->text('comentarios')->nullable();
            $table->boolean('contactado')->default(false);
            $table->timestamps();

            $table->foreign('contrato_id')->references('id')->on('contratos')->cascadeOnDelete();
            $table->foreign('cuota_id')->references('id')->on('contrato_cuotas')->nullOnDelete();
            $table->foreign('usuario_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_seguimientos');
        Schema::dropIfExists('contrato_cuotas');
        Schema::dropIfExists('contratos');
    }
};
