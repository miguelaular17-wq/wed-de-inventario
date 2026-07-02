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
        Schema::create('inventario_v2.historial_ventas_mensuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('inventario_v2.productos')->onDelete('cascade');
            $table->string('sede', 50);
            $table->string('anio_mes', 7);
            $table->integer('cantidad');
            $table->timestamps();

            $table->unique(['sede', 'producto_id', 'anio_mes'], 'historial_ventas_mensuales_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventario_v2.historial_ventas_mensuales');
    }
};
