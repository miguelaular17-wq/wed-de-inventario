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
        Schema::create('historial_cobranzas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_registro');
            $table->string('sede_nombre')->nullable();
            $table->string('codigo_cliente')->nullable();
            $table->string('nombre_cliente')->nullable();
            $table->string('id_documento')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->string('tipo_cxc')->nullable();
            $table->string('numero_documento')->nullable();
            $table->decimal('monto_neto', 15, 2)->default(0);
            $table->decimal('saldo', 15, 2)->default(0);
            $table->integer('dias_deuda')->default(0);
            $table->string('estatus')->nullable();
            $table->string('usuario')->nullable();
            $table->string('estacion')->nullable();
            $table->string('codigo_caja')->nullable();
            $table->timestamps();

            $table->index(['fecha_registro', 'sede_nombre']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_cobranzas');
    }
};
