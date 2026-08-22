<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nomina_abonos_sueldo')) {
            return;
        }

        Schema::create('nomina_abonos_sueldo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empleado_id');
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->date('quincena_inicio');
            $table->date('quincena_fin');
            $table->string('etiqueta', 64);
            $table->string('estado', 16)->default('PENDIENTE');
            $table->unsignedBigInteger('nomina_periodo_id')->nullable();
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_abonos_sueldo');
    }
};
