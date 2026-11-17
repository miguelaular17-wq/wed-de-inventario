<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nomina_st_egreso_exclusiones')) {
            return;
        }

        Schema::create('nomina_st_egreso_exclusiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_id')->constrained('nomina_periodos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('nomina_empleados')->cascadeOnDelete();
            $table->unsignedBigInteger('flujo_caja_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['periodo_id', 'flujo_caja_id']);
            $table->index(['periodo_id', 'empleado_id']);
            $table->index('flujo_caja_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_st_egreso_exclusiones');
    }
};
