<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nomina_st_egreso_ajustes')) {
            return;
        }

        Schema::create('nomina_st_egreso_ajustes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_id')->constrained('nomina_periodos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('nomina_empleados')->cascadeOnDelete();
            $table->decimal('monto', 12, 2)->comment('USD; positivo suma a egresos 058, negativo resta');
            $table->string('motivo', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['periodo_id', 'empleado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_st_egreso_ajustes');
    }
};
