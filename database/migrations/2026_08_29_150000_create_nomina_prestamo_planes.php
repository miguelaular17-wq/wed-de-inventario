<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nomina_prestamo_planes')) {
            return;
        }

        Schema::create('nomina_prestamo_planes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('prestamo_id');
            $table->unsignedBigInteger('cuota_id');
            $table->date('quincena_inicio');
            $table->date('quincena_fin');
            $table->string('etiqueta', 64);
            $table->decimal('monto', 12, 2);
            $table->string('destino', 16)->default('NOMINA');
            $table->string('estado', 16)->default('PENDIENTE');
            $table->unsignedBigInteger('nomina_periodo_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['cuota_id', 'quincena_inicio']);
            $table->index(['empleado_id', 'estado']);
            $table->index('nomina_periodo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_prestamo_planes');
    }
};
