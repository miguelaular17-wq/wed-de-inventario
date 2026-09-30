<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nomina_comisiones_marca')) {
            return;
        }

        Schema::create('nomina_comisiones_marca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nomina_empleado_id')->constrained('nomina_empleados')->cascadeOnDelete();
            $table->string('marca', 20);
            $table->date('fecha');
            $table->decimal('monto_bs', 14, 2);
            $table->decimal('tasa', 14, 4);
            $table->decimal('monto_usd', 14, 2);
            $table->string('nota', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fecha', 'marca']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_comisiones_marca');
    }
};
