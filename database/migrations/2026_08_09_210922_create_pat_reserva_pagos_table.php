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
        Schema::create('pat_reserva_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('pat_reservas')->onDelete('cascade');
            $table->decimal('monto_pagado', 10, 2);
            $table->string('forma_pago')->nullable();
            $table->date('fecha_pago');
            $table->decimal('tasa_cambio', 12, 4)->nullable();
            $table->string('banco_origen')->nullable();
            $table->string('banco_destino')->nullable();
            $table->string('referencia')->nullable();
            $table->text('comentario')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pat_reserva_pagos');
    }
};
