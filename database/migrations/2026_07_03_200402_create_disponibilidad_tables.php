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
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->string('banco');
            $table->string('titular');
            $table->string('color_tc')->nullable(); // color hex o nombre css
            $table->decimal('bs_tc', 20, 2)->default(0);
            $table->decimal('bs_disponibles', 20, 2)->default(0);
            $table->decimal('usd_tc', 20, 2)->default(0);
            $table->decimal('usd_disp', 20, 2)->default(0);
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('finanzas_resumen', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique(); // Una fila por día o una única fila
            $table->decimal('tasa_bcv_usd', 15, 2)->default(0);
            $table->decimal('saldo_inicial', 20, 2)->default(0);
            $table->decimal('queda_dia_anterior', 20, 2)->default(0);
            $table->decimal('porcentaje_total_diferencial', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finanzas_resumen');
        Schema::dropIfExists('cuentas_bancarias');
    }
};
