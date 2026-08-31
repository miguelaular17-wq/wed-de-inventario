<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nomina_ajustes')) {
            return;
        }

        Schema::create('nomina_ajustes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empleado_id');
            $table->date('fecha');
            $table->string('tipo', 16);
            $table->string('destino', 16)->default('NOMINA');
            $table->decimal('monto', 12, 2);
            $table->date('quincena_inicio');
            $table->date('quincena_fin');
            $table->string('etiqueta', 64);
            $table->string('estado', 16)->default('PENDIENTE');
            $table->unsignedBigInteger('nomina_periodo_id')->nullable();
            $table->text('motivo');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['empleado_id', 'estado']);
            $table->index(['destino', 'tipo', 'estado']);
            $table->index('nomina_periodo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_ajustes');
    }
};
